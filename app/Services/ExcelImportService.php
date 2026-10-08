<?php

namespace App\Services;

use App\Exceptions\ImportFileException;
use App\Exceptions\InterventionRuleViolation;
use App\Imports\ColumnMapper;
use App\Imports\MappedHeader;
use App\Imports\RowNormalizer;
use App\Imports\SpreadsheetReader;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Spec rule 9: read, check and stage a spreadsheet; nothing reaches the beneficiary tables until confirm(). */
final class ExcelImportService
{
    private const EXTENSIONS = ['xlsx', 'xls', 'csv'];

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly ColumnMapper $mapper,
        private readonly InterventionAssignment $assignment,
        private readonly ClaimService $claims,
    ) {}

    public function stage(UploadedFile $file, User $actor): ImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new ImportFileException('Upload an .xlsx, .xls or .csv file.');
        }
        if ($file->getSize() > self::maxKilobytes() * 1024) {
            throw new ImportFileException(self::tooLargeMessage());
        }

        $maxRows = (int) config('agapay.import.max_rows', 20000);
        $tooManyRows = new ImportFileException('The sheet has more than '.number_format($maxRows).' rows. Split it into smaller files.');
        // Checked from the file's index first: loading a sheet far over the limit could run PHP out of memory.
        if ($this->reader->rowCount($file->getRealPath(), $extension) > $maxRows + ColumnMapper::HEADER_SCAN_ROWS) {
            throw $tooManyRows;
        }

        $rows = $this->reader->read($file->getRealPath(), $extension);
        $header = $this->mapper->map($rows);
        $this->ensureRequiredColumns($header);

        $dataRows = array_filter(
            array_slice($rows, $header->headerIndex + 1, preserve_keys: true),
            fn (array $row) => array_filter($row, fn ($cell) => $cell !== null) !== [],
        );
        if ($dataRows === []) {
            throw new ImportFileException('The sheet has no data rows under its header.');
        }
        if (count($dataRows) > $maxRows) {
            throw $tooManyRows;
        }

        $normalizer = new RowNormalizer(
            Barangay::orderBy('name')->pluck('name', 'id'),
            Intervention::all(['id', 'source', 'name']),
            DistributionCycle::all(['id', 'code']),
            DistributionCycle::current()?->id,
        );
        $staged = [];
        foreach ($dataRows as $index => $cells) {
            $staged[] = ['row_number' => $index + 1, ...$normalizer->normalize($cells, $header, $index + 1)];
        }
        $staged = $this->dedupe($staged);

        $counts = array_fill_keys([ImportRow::READY, ImportRow::FLAGGED, ImportRow::UPDATE, ImportRow::DUPLICATE, ImportRow::UNREADABLE], 0);
        foreach ($staged as $row) {
            $counts[$row['status']]++;
        }

        return DB::transaction(function () use ($file, $extension, $actor, $header, $staged, $counts) {
            $batch = ImportBatch::create([
                'user_id' => $actor->id,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'stored_path' => $file->storeAs('imports', Str::uuid().'.'.$extension, 'local'),
                'status' => ImportBatch::STAGED,
                'header_row' => $header->headerIndex + 1,
                'mapping' => $header->columns,
                'feedback' => $this->feedback($header, $counts),
                'counts' => $counts,
            ]);

            $now = now();
            foreach (array_chunk($staged, 500) as $chunk) {
                ImportRow::insert(array_map(fn (array $row) => [
                    'import_batch_id' => $batch->id,
                    'row_number' => $row['row_number'],
                    'status' => $row['status'],
                    'data' => json_encode($row['data']),
                    'issues' => json_encode($row['issues']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            AuditLogger::record('Uploaded Excel File', $batch, null, [], ['rows' => array_sum($counts), ...$counts], $actor);

            return $batch;
        });
    }

    /**
     * Confirm & Import (spec rule 9): replays the staged rows in one transaction. A row that breaks a
     * distribution rule aborts everything with "Row {n}: …"; rows that became duplicates since staging are skipped.
     *
     * @return array{created: int, updated: int, records: int, skipped: int}
     */
    public function confirm(ImportBatch $batch, User $actor): array
    {
        $result = DB::transaction(function () use ($batch, $actor) {
            $batch = $this->lockStaged($batch);
            $result = ['created' => 0, 'updated' => 0, 'records' => 0, 'skipped' => 0];
            $cycles = DistributionCycle::all()->keyBy('id');
            $interventions = Intervention::all()->keyBy('id');

            $rows = $batch->rows()->whereIn('status', [ImportRow::READY, ImportRow::FLAGGED, ImportRow::UPDATE])->orderBy('row_number')->get();
            foreach ($rows as $row) {
                try {
                    $beneficiary = $row->status === ImportRow::UPDATE ? $this->recordNumber($row, $actor) : $this->createProfile($row, $actor);
                    if ($beneficiary === null) {
                        $row->update(['status' => ImportRow::DUPLICATE, 'issues' => ['Already in AGAPAY when the import was confirmed']]);
                        $result['skipped']++;

                        continue;
                    }
                    $result[$row->status === ImportRow::UPDATE ? 'updated' : 'created']++;

                    $data = $row->data;
                    $intervention = $interventions[$data['intervention_id'] ?? 0] ?? null;
                    $cycle = $cycles[$data['cycle_id'] ?? 0] ?? null;
                    if ($intervention && $cycle) {
                        $attrs = ['quantity' => $data['quantity'] ?? null];
                        $record = $this->assignment->assign($beneficiary, $intervention, $cycle, $attrs, $actor);
                        if ($data['date_distributed'] ?? null) {
                            $this->claims->claim($record, $actor, [...$attrs, 'date_distributed' => $data['date_distributed']], historical: true);
                        }
                        $result['records']++;
                    }

                    $row->update(['beneficiary_id' => $beneficiary->id]);
                } catch (UniqueConstraintViolationException $e) {
                    if (! Beneficiary::isRsbsaClash($e)) {
                        throw $e;
                    }
                    // Registered by someone else between the check and the insert.
                    throw new ImportFileException("Row {$row->row_number}: RSBSA No. {$row->data['rsbsa_number']} was registered by someone else meanwhile.");
                } catch (InterventionRuleViolation|ImportFileException $e) {
                    throw new ImportFileException("Row {$row->row_number}: {$e->getMessage()}");
                } catch (ValidationException $e) {
                    throw new ImportFileException("Row {$row->row_number}: ".collect($e->errors())->flatten()->first());
                }
            }

            $batch->update(['status' => ImportBatch::IMPORTED, 'imported_at' => now()]);
            AuditLogger::record('Imported Excel File', $batch, null, ['status' => ImportBatch::STAGED], $result, $actor);

            return $result;
        });
        $this->deleteUpload($batch);

        return $result;
    }

    /** Drops a staged batch; its rows stay for the record but can no longer be imported. */
    public function discard(ImportBatch $batch, User $actor): void
    {
        DB::transaction(function () use ($batch, $actor) {
            $batch = $this->lockStaged($batch);
            $batch->update(['status' => ImportBatch::DISCARDED]);
            AuditLogger::record('Discarded Excel Import', $batch, null, ['status' => ImportBatch::STAGED], ['status' => ImportBatch::DISCARDED], $actor);
        });
        $this->deleteUpload($batch);
    }

    /** The masterlist holds personal data: once imported or discarded only its staged rows are kept as the record. */
    private function deleteUpload(ImportBatch $batch): void
    {
        if ($batch->stored_path) {
            Storage::disk('local')->delete($batch->stored_path);
        }
    }

    public static function maxKilobytes(): int
    {
        return (int) config('agapay.import.max_kilobytes', 25 * 1024);
    }

    public static function tooLargeMessage(): string
    {
        return 'The file is larger than '.round(self::maxKilobytes() / 1024).' MB.';
    }

    /** The batch row, locked; a double-clicked confirm or a back-button resubmit waits here and then finds it imported. */
    private function lockStaged(ImportBatch $batch): ImportBatch
    {
        $batch = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
        if ($batch->status !== ImportBatch::STAGED) {
            throw new ImportFileException($batch->status === ImportBatch::IMPORTED ? 'This import was already confirmed.' : 'This import was discarded.');
        }

        return $batch;
    }

    /** A new profile from a ready/flagged row, or null when the same person was registered after staging. */
    private function createProfile(ImportRow $row, User $actor): ?Beneficiary
    {
        $data = $row->data;
        if (Beneficiary::isAlreadyRegistered($data['first_name'], $data['last_name'], $data['birthdate'], $data['barangay_id'])) {
            return null;
        }
        $this->ensureNumberFree($data['rsbsa_number']);

        return Beneficiary::create([
            ...Arr::only($data, ['first_name', 'middle_name', 'last_name', 'birthdate', 'address', 'barangay_id', 'contact_number', 'farm_location', 'crop_type', 'rsbsa_number']),
            // A row without a number is imported as is: the farmer shows "N/A" and can still get LGU programs.
            'rsbsa_status' => Beneficiary::RSBSA_REGISTERED,
            'source' => Beneficiary::SOURCE_IMPORT,
            'created_by' => $actor->id,
        ]);
    }

    /** Gives an existing profile its RSBSA No.; null when it got one (or was archived) after staging. */
    private function recordNumber(ImportRow $row, User $actor): ?Beneficiary
    {
        $beneficiary = Beneficiary::whereKey($row->data['beneficiary_id'] ?? 0)->lockForUpdate()->first();
        if ($beneficiary === null || $beneficiary->rsbsa_number) {
            return null;
        }
        $this->ensureNumberFree($row->data['rsbsa_number']);

        $keys = ['rsbsa_number'];
        $old = $beneficiary->only($keys);
        $beneficiary->forceFill([
            'rsbsa_number' => Beneficiary::normalizeRsbsa($row->data['rsbsa_number']),   // saved quietly: no model hook
            'updated_by' => $actor->id,
        ])->saveQuietly();
        AuditLogger::record('Recorded RSBSA Number', $beneficiary, null, $old, $beneficiary->only($keys), $actor);

        return $beneficiary;
    }

    private function ensureNumberFree(?string $rsbsaNumber): void
    {
        $owner = $rsbsaNumber
            ? Beneficiary::withTrashed()->whereRaw('UPPER(TRIM(rsbsa_number)) = ?', [Beneficiary::normalizeRsbsa($rsbsaNumber)])->first()
            : null;
        if ($owner) {
            throw new ImportFileException("RSBSA No. {$rsbsaNumber} is already used by {$owner->fullName()}.");
        }
    }

    private function ensureRequiredColumns(MappedHeader $header): void
    {
        $hasName = $header->has('full_name') || ($header->has('first_name') && $header->has('last_name'));

        if (! $hasName || ! $header->has('birthdate') || ! $header->has('barangay')) {
            throw new ImportFileException('The sheet needs Name (or First Name and Last Name), Birthdate and Barangay columns.');
        }
    }

    /**
     * Same person = same RSBSA No. (trimmed, any case) or same first + last name (any case), birthdate and barangay —
     * checked against active profiles and against earlier rows of the file.
     *
     * @param  list<array{row_number: int, status: string, data: array, issues: list<string>}>  $staged
     */
    private function dedupe(array $staged): array
    {
        $byRsbsa = [];
        $byIdentity = [];
        // RSBSA numbers are unique across archived profiles too; identity only counts active ones.
        foreach (Beneficiary::withTrashed()->get(['id', 'first_name', 'middle_name', 'last_name', 'birthdate', 'barangay_id', 'rsbsa_number', 'deleted_at']) as $b) {
            if ($b->rsbsa_number) {
                $byRsbsa[mb_strtolower(trim($b->rsbsa_number))] = $b;
            }
            if (! $b->trashed()) {
                $byIdentity[self::identity($b->first_name, $b->last_name, $b->birthdate->toDateString(), $b->barangay_id)] = $b;
            }
        }

        $seenRsbsa = [];
        $seenIdentity = [];
        foreach ($staged as &$row) {
            if ($row['status'] === ImportRow::UNREADABLE) {
                continue;
            }
            $data = $row['data'];
            $rsbsa = $data['rsbsa_number'] ? mb_strtolower($data['rsbsa_number']) : null;
            $identity = self::identity($data['first_name'], $data['last_name'], $data['birthdate'], $data['barangay_id']);

            $samePerson = fn (string $first, string $last) => mb_strtolower($first) === mb_strtolower($data['first_name'])
                && mb_strtolower($last) === mb_strtolower($data['last_name']);
            $exclude = function (string $reason) use (&$row) {
                $row['status'] = ImportRow::UNREADABLE;
                $row['issues'] = [$reason];
            };
            $skip = function (string $note) use (&$row) {
                $row['status'] = ImportRow::DUPLICATE;
                $row['issues'] = [$note];
            };

            if ($rsbsa && isset($byRsbsa[$rsbsa])) {
                $owner = $byRsbsa[$rsbsa];
                match (true) {
                    $owner->trashed() => $exclude("RSBSA No. {$data['rsbsa_number']} belongs to the archived profile of {$owner->fullName()}"),
                    $samePerson($owner->first_name, $owner->last_name) => $skip('Already in AGAPAY'),
                    default => $exclude("RSBSA No. {$data['rsbsa_number']} belongs to {$owner->fullName()}"),
                };
            } elseif (isset($byIdentity[$identity])) {
                $existing = $byIdentity[$identity];
                if ($existing->rsbsa_number && $rsbsa && mb_strtolower(trim($existing->rsbsa_number)) !== $rsbsa) {
                    $exclude("{$existing->fullName()} already has RSBSA No. {$existing->rsbsa_number} in AGAPAY");
                } elseif ($existing->rsbsa_number || ! $rsbsa || isset($seenRsbsa[$rsbsa])) {
                    $skip('Already in AGAPAY');
                } else {
                    $row['status'] = ImportRow::UPDATE;
                    $row['data']['beneficiary_id'] = $existing->id;
                }
            } elseif ($rsbsa && isset($seenRsbsa[$rsbsa])) {
                $first = $seenRsbsa[$rsbsa];
                $samePerson($first['first_name'], $first['last_name'])
                    ? $skip("Same person as row {$first['row']}")
                    : $exclude("RSBSA No. {$data['rsbsa_number']} is also on row {$first['row']} ({$first['name']})");
            } elseif (isset($seenIdentity[$identity])) {
                $skip("Same person as row {$seenIdentity[$identity]}");
            }

            if ($row['status'] !== ImportRow::UNREADABLE) {
                $seenIdentity[$identity] ??= $row['row_number'];
                if ($rsbsa) {
                    $seenRsbsa[$rsbsa] ??= [
                        'row' => $row['row_number'], 'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                        'name' => implode(' ', array_filter([$data['first_name'], $data['middle_name'], $data['last_name']])),
                    ];
                }
            }
        }

        return $staged;
    }

    private static function identity(?string $first, ?string $last, ?string $birthdate, ?int $barangayId): string
    {
        return mb_strtolower((string) $first).'|'.mb_strtolower((string) $last).'|'.$birthdate.'|'.$barangayId;
    }

    /** @return list<array{ok: bool, text: string}> Processing Feedback lines (Figma 470:540). */
    private function feedback(MappedHeader $header, array $counts): array
    {
        $readable = array_sum($counts) - $counts[ImportRow::UNREADABLE];
        $labels = collect($header->columns)->sortKeys()->map(fn (string $field) => ColumnMapper::label($field))->join(', ');

        $lines = [['ok' => true, 'text' => $readable.' '.Str::plural('row', $readable)." matched automatically ({$labels})"]];

        foreach ($header->corrections as $correction) {
            $lines[] = ['ok' => true, 'text' => "Column shift auto-corrected: '{$correction['header']}' -> mapped to '".ColumnMapper::label($correction['field'])."'"];
        }

        if ($n = $counts[ImportRow::UPDATE]) {
            $lines[] = ['ok' => true, 'text' => $n === 1 ? '1 existing profile will receive its RSBSA No.' : "{$n} existing profiles will receive their RSBSA No."];
        }
        if ($n = $counts[ImportRow::DUPLICATE]) {
            $lines[] = ['ok' => false, 'text' => $n.' '.Str::plural('duplicate', $n).' skipped (already in AGAPAY or repeated in the file)'];
        }
        if ($n = $counts[ImportRow::UNREADABLE]) {
            // Not every exclusion is a corrupted cell (under-age, unknown barangay…); the preview gives each reason.
            $lines[] = ['ok' => false, 'text' => $n.' '.Str::plural('row', $n).' excluded — see the reasons in the preview'];
        }

        return $lines;
    }
}
