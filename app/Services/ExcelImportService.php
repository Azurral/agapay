<?php

namespace App\Services;

use App\Exceptions\ImportFileException;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Spec rule 9: read, check and stage a spreadsheet; nothing reaches the beneficiary tables until confirm(). */
final class ExcelImportService
{
    private const EXTENSIONS = ['xlsx', 'xls', 'csv'];

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly ColumnMapper $mapper,
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
        $maxRows = (int) config('agapay.import.max_rows', 20000);
        if (count($dataRows) > $maxRows) {
            throw new ImportFileException('The sheet has more than '.number_format($maxRows).' rows. Split it into smaller files.');
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

    public static function maxKilobytes(): int
    {
        return (int) config('agapay.import.max_kilobytes', 25 * 1024);
    }

    public static function tooLargeMessage(): string
    {
        return 'The file is larger than '.round(self::maxKilobytes() / 1024).' MB.';
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
        foreach (Beneficiary::get(['id', 'first_name', 'middle_name', 'last_name', 'birthdate', 'barangay_id', 'rsbsa_number']) as $b) {
            if ($b->rsbsa_number) {
                $byRsbsa[mb_strtolower(trim($b->rsbsa_number))] = $b;
            }
            $byIdentity[self::identity($b->first_name, $b->last_name, $b->birthdate->toDateString(), $b->barangay_id)] = $b;
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

            if ($rsbsa && isset($byRsbsa[$rsbsa])) {
                $owner = $byRsbsa[$rsbsa];
                if (mb_strtolower($owner->first_name) === mb_strtolower($data['first_name']) && mb_strtolower($owner->last_name) === mb_strtolower($data['last_name'])) {
                    $row['status'] = ImportRow::DUPLICATE;
                } else {
                    $row['status'] = ImportRow::UNREADABLE;
                    $row['issues'] = ["RSBSA No. {$data['rsbsa_number']} belongs to {$owner->fullName()}"];
                }
            } elseif (isset($byIdentity[$identity])) {
                $existing = $byIdentity[$identity];
                if (! $existing->rsbsa_number && $data['rsbsa_number'] && ! isset($seenRsbsa[$rsbsa])) {
                    $row['status'] = ImportRow::UPDATE;
                    $row['data']['beneficiary_id'] = $existing->id;
                } else {
                    $row['status'] = ImportRow::DUPLICATE;
                }
            } elseif (($rsbsa && isset($seenRsbsa[$rsbsa])) || isset($seenIdentity[$identity])) {
                $row['status'] = ImportRow::DUPLICATE;
            }

            if ($row['status'] !== ImportRow::UNREADABLE) {
                $seenIdentity[$identity] = true;
                if ($rsbsa) {
                    $seenRsbsa[$rsbsa] = true;
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
        if ($n = $counts[ImportRow::FLAGGED]) {
            $lines[] = ['ok' => false, 'text' => $n.' '.Str::plural('row', $n).' missing RSBSA No. — flagged for manual review'];
        }
        if ($n = $counts[ImportRow::DUPLICATE]) {
            $lines[] = ['ok' => false, 'text' => $n.' '.Str::plural('duplicate', $n).' skipped (already in AGAPAY or repeated in the file)'];
        }
        if ($n = $counts[ImportRow::UNREADABLE]) {
            $lines[] = ['ok' => false, 'text' => $n.' '.Str::plural('row', $n).' unreadable (corrupted cells) — excluded, see log'];
        }

        return $lines;
    }
}
