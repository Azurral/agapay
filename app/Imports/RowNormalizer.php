<?php

namespace App\Imports;

use App\Models\DistributionCycle;
use App\Models\Intervention;
use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Turns one spreadsheet row into clean beneficiary data (spec rule 9). Blocking problems make the row
 * "unreadable"; minor ones (an ignored contact number, a skipped intervention) are kept as issues.
 * Duplicates are judged by ExcelImportService, which sees the whole file.
 */
final class RowNormalizer
{
    /** Surname particles that stay with the last name ("Dela Cruz", "de los Santos"). */
    private const PARTICLES = ['de', 'del', 'dela', 'della', 'delos', 'di', 'da', 'dos', 'la', 'las', 'los', 'san', 'santa', 'santo', 'sta', 'sto', 'van', 'von'];

    /** Column sizes of the beneficiaries table (as in BeneficiaryRules); MariaDB refuses longer values. */
    private const MAX_LENGTHS = [
        'first_name' => 100, 'middle_name' => 100, 'last_name' => 100,
        'sitio' => 100, 'crop_type' => 255, 'rsbsa_number' => 50,
    ];

    private const DATE_FORMATS = ['Y-m-d', 'm/d/Y', 'M j, Y', 'F j, Y', 'M d, Y', 'F d, Y', 'j M Y', 'd-M-Y', 'Y/m/d'];

    /**
     * @param  Collection<int, string>  $barangays  id => name
     * @param  Collection<int, Intervention>  $interventions
     * @param  Collection<int, DistributionCycle>  $cycles
     */
    public function __construct(
        private readonly Collection $barangays,
        private readonly Collection $interventions,
        private readonly Collection $cycles,
        private readonly ?int $currentCycleId,
    ) {}

    /** @return array{status: string, data: array<string, mixed>, issues: list<string>} */
    public function normalize(array $cells, MappedHeader $header, int $rowNumber): array
    {
        $raw = [];
        $blocking = [];
        $corrupt = [];

        foreach ($header->columns as $index => $field) {
            $value = $cells[$index] ?? null;
            if (SpreadsheetReader::isError($value)) {
                $blocking[] = 'Corrupted cell in '.ColumnMapper::label($field);
                $corrupt[$field] = true;
                $value = null;
            }
            $raw[$field] = $value === null ? null : self::clean($value);
        }

        $issues = [];
        $data = $this->names($raw);
        if (($data['first_name'] === null || $data['last_name'] === null) && ! array_intersect_key($corrupt, array_flip(['first_name', 'last_name', 'full_name']))) {
            $blocking[] = 'Missing name';
        }

        $data['birthdate'] = null;
        if ($raw['birthdate'] ?? null) {
            $data['birthdate'] = self::date($raw['birthdate']);
            if ($data['birthdate'] === null) {
                $blocking[] = "Unreadable birthdate '{$raw['birthdate']}'".(self::isYear($raw['birthdate']) ? ' (year only)' : '');
            } elseif (CarbonImmutable::parse($data['birthdate'])->age < 18) {
                $blocking[] = 'Under 18 (born '.CarbonImmutable::parse($data['birthdate'])->format('M j, Y').')';
            }
        } elseif (! isset($corrupt['birthdate'])) {
            $blocking[] = 'Missing birthdate';
        }

        $data['barangay_id'] = null;
        $data['barangay'] = null;
        if ($raw['barangay'] ?? null) {
            $data['barangay_id'] = $this->barangayId($raw['barangay']);
            $data['barangay'] = $this->barangays[$data['barangay_id']] ?? null;
            if ($data['barangay_id'] === null) {
                $blocking[] = "Unknown barangay '{$raw['barangay']}'";
            }
        } elseif (! isset($corrupt['barangay'])) {
            $blocking[] = 'Missing barangay';
        }

        // A one-line address column becomes the sitio/purok; a blank one falls back to the barangay.
        $data['sitio'] = $raw['address'] ?? null ?: ($data['barangay'] ? "Barangay {$data['barangay']}" : null);
        $data['farm_area_ha'] = null;
        if (($raw['farm_area_ha'] ?? null) !== null) {
            $area = str_replace(',', '', preg_replace('/\s*(ha|hectares?)\.?$/i', '', $raw['farm_area_ha']));
            if (is_numeric($area) && (float) $area >= 0 && (float) $area <= 9999.99) {
                $data['farm_area_ha'] = $area;
            } else {
                $issues[] = "Farm area '{$raw['farm_area_ha']}' ignored (not a number of hectares)";
            }
        }
        $data['crop_type'] = $raw['crop_type'] ?? null;
        $data['rsbsa_number'] = $raw['rsbsa_number'] ?? null;
        $data['contact_number'] = null;
        if ($raw['contact_number'] ?? null) {
            $data['contact_number'] = self::contact($raw['contact_number']);
            if ($data['contact_number'] === null) {
                $issues[] = "Contact number '{$raw['contact_number']}' ignored";
            }
        }

        $this->intervention($raw, $data, $issues);

        foreach (self::MAX_LENGTHS as $field => $max) {
            if (mb_strlen((string) ($data[$field] ?? '')) > $max) {
                $blocking[] = ColumnMapper::label($field)." is longer than {$max} characters";
            }
        }

        if ($blocking !== []) {
            return ['status' => 'unreadable', 'data' => $data, 'issues' => $blocking];
        }

        // DA programs need an RSBSA number; the profile is still imported (shown as N/A).
        if ($data['intervention_id'] && ! $data['rsbsa_number'] && $this->interventions->firstWhere('id', $data['intervention_id'])?->requiresRsbsa()) {
            $issues[] = 'Intervention skipped: '.Intervention::RSBSA_REQUIRED_MESSAGE;
            $data['intervention_id'] = null;
        }

        return ['status' => 'ready', 'data' => $data, 'issues' => $issues];
    }

    /** Trimmed, single-spaced. */
    public static function clean(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    /** Excel serial numbers and common office formats → Y-m-d; null when it can't be read. */
    public static function date(string $value): ?string
    {
        // "1975" is a year, not Excel day 1975 (May 1905).
        if (ctype_digit($value) && ! self::isYear($value) && (int) $value > 0 && (int) $value < 80000) {
            return ExcelDate::excelToDateTimeObject((int) $value)->format('Y-m-d');
        }

        $formats = self::DATE_FORMATS;
        // Day-first only when the first number can't be a month (17/02/1982).
        if (preg_match('#^(\d{1,2})/\d{1,2}/\d{4}$#', $value, $m) && (int) $m[1] > 12) {
            $formats[] = 'd/m/Y';
        }

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat('!'.$format, $value);
            $errors = DateTime::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private static function isYear(string $value): bool
    {
        return preg_match('/^\d{4}$/', $value) === 1 && (int) $value >= 1900 && (int) $value <= 2100;
    }

    /** "9171234567" (a number that lost its 0) → "09171234567"; unusable values → null. */
    public static function contact(string $value): ?string
    {
        if (preg_match('/^09\d{2}-?\d{3}-?\d{4}$/', $value)) {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0'.$digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '639')) {
            return '0'.substr($digits, 2);
        }

        return strlen($digits) === 11 && str_starts_with($digits, '09') ? $digits : null;
    }

    /** @return array{first_name: ?string, middle_name: ?string, last_name: ?string} */
    private function names(array $raw): array
    {
        if (($raw['first_name'] ?? null) || ($raw['last_name'] ?? null)) {
            return ['first_name' => $raw['first_name'] ?? null, 'middle_name' => $raw['middle_name'] ?? null, 'last_name' => $raw['last_name'] ?? null];
        }

        $full = $raw['full_name'] ?? null;
        if ($full === null) {
            return ['first_name' => null, 'middle_name' => null, 'last_name' => null];
        }

        // "Dela Cruz, Juan Abenoja"
        if (str_contains($full, ',')) {
            [$last, $rest] = array_map(fn ($part) => self::clean($part), explode(',', $full, 2));
            $words = $rest ? explode(' ', $rest) : [];

            return ['first_name' => $words[0] ?? null, 'middle_name' => self::clean(implode(' ', array_slice($words, 1))), 'last_name' => $last];
        }

        // "Juan Abenoja Dela Cruz": the last word plus any particles before it is the surname.
        $words = explode(' ', $full);
        if (count($words) < 2) {
            return ['first_name' => $words[0], 'middle_name' => null, 'last_name' => null];
        }
        $start = count($words) - 1;
        while ($start > 1 && in_array(mb_strtolower($words[$start - 1]), self::PARTICLES, true)) {
            $start--;
        }

        return [
            'first_name' => $words[0],
            'middle_name' => self::clean(implode(' ', array_slice($words, 1, $start - 1))),
            'last_name' => implode(' ', array_slice($words, $start)),
        ];
    }

    private function barangayId(string $value): ?int
    {
        $key = self::barangayKey($value);
        $best = null;
        $bestDistance = 3;

        foreach ($this->barangays as $id => $name) {
            $distance = levenshtein($key, self::barangayKey($name));
            if ($distance === 0) {
                return $id;
            }
            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$id, $distance];
            }
        }

        return $best;
    }

    private static function barangayKey(string $value): string
    {
        $value = preg_replace('/\b(barangay|brgy|bgy)\b\.?/u', ' ', mb_strtolower($value));

        return preg_replace('/[^a-z]/', '', $value);
    }

    /** Optional DA/LGU columns: resolves the program and cycle, or explains why the record is skipped. */
    private function intervention(array $raw, array &$data, array &$issues): void
    {
        $data['intervention_id'] = null;
        $data['cycle_id'] = null;
        $data['quantity'] = null;
        $data['date_distributed'] = null;

        $cell = $raw['intervention'] ?? null;
        if ($cell === null) {
            return;
        }

        $source = null;
        $name = $cell;
        if (preg_match('/^(DA|LGU)\b\s*[-:]?\s*(.+)$/i', $cell, $m)) {
            [$source, $name] = [strtolower($m[1]), trim($m[2])];
        }
        $matches = $this->interventions->filter(fn ($i) => mb_strtolower($i->name) === mb_strtolower($name) && ($source === null || $i->source === $source));

        if ($matches->isEmpty()) {
            $issues[] = "Intervention skipped: unknown program '{$cell}'";

            return;
        }
        if ($matches->count() > 1) {
            $issues[] = "Intervention skipped: '{$cell}' is both a DA and an LGU program — write DA or LGU before it";

            return;
        }

        $cycleId = $this->currentCycleId;
        if ($raw['cycle'] ?? null) {
            $cycleId = $this->cycles->first(fn ($c) => mb_strtolower($c->code) === mb_strtolower($raw['cycle']))?->id;
            if ($cycleId === null) {
                $issues[] = "Intervention skipped: unknown cycle '{$raw['cycle']}'";

                return;
            }
        }
        if ($cycleId === null) {
            $issues[] = 'Intervention skipped: no distribution cycle exists yet';

            return;
        }

        $data['intervention_id'] = $matches->first()->id;
        $data['cycle_id'] = $cycleId;

        if ($raw['quantity'] ?? null) {
            is_numeric($raw['quantity']) && (float) $raw['quantity'] >= 0
                ? $data['quantity'] = $raw['quantity']
                : $issues[] = "Quantity '{$raw['quantity']}' ignored";
        }

        if ($raw['date_distributed'] ?? null) {
            $date = self::date($raw['date_distributed']);
            $date && $date <= now()->toDateString()
                ? $data['date_distributed'] = $date
                : $issues[] = "Distribution date '{$raw['date_distributed']}' ignored";
        }
    }
}
