<?php

namespace App\Imports;

use App\Exceptions\ImportFileException;

/** Finds the header row and maps its cells to fields by synonym, then by fuzzy match (spec rule 9). */
final class ColumnMapper
{
    /** field => synonyms (compared after lower-casing and dropping everything but letters and digits) */
    public const SYNONYMS = [
        'first_name' => ['first name', 'firstname', 'given name', 'fname'],
        'middle_name' => ['middle name', 'middlename', 'mname', 'mi', 'middle initial'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name', 'lname'],
        'full_name' => ['name', 'full name', 'fullname', 'farmer name', 'farmer', 'beneficiary', 'beneficiary name'],
        'birthdate' => ['birthdate', 'birthday', 'birth date', 'date of birth', 'dob'],
        'address' => ['address', 'purok', 'sitio', 'street', 'house no'],
        'barangay' => ['barangay', 'brgy', 'bgy', 'barangay name'],
        'contact_number' => ['contact', 'contact no', 'contact number', 'mobile', 'mobile no', 'cellphone', 'cellphone no', 'cp no', 'phone'],
        'farm_area_ha' => ['farm area', 'farm area ha', 'area', 'area ha', 'hectares', 'hectare', 'farm size', 'ha', 'farm'],
        'crop_type' => ['crop', 'crop type', 'commodity'],
        'rsbsa_number' => ['rsbsa', 'rsbsa no', 'rsbsa number', 'rsbsa id', 'reference no'],
        'intervention' => ['intervention', 'assistance', 'program'],
        'quantity' => ['qty', 'quantity'],
        'cycle' => ['cycle', 'batch', 'batch cycle'],
        'date_distributed' => ['date distributed', 'date released', 'date given', 'distribution date'],
    ];

    public const LABELS = [
        'first_name' => 'First Name', 'middle_name' => 'Middle Name', 'last_name' => 'Last Name', 'full_name' => 'Name',
        'birthdate' => 'Birthdate', 'address' => 'Address', 'sitio' => 'Address', 'barangay' => 'Barangay', 'contact_number' => 'Contact No.',
        'farm_area_ha' => 'Farm Area', 'crop_type' => 'Crop Type', 'rsbsa_number' => 'RSBSA No.',
        'intervention' => 'Intervention', 'quantity' => 'Quantity', 'cycle' => 'Cycle', 'date_distributed' => 'Date Distributed',
    ];

    public const HEADER_SCAN_ROWS = 15;

    /**
     * The header is the row (of the first 15) that maps the most columns; on a tie the later row wins, so the
     * column names under a row of group headings ("Name" over Last / First / Middle) are chosen.
     *
     * @param  list<list<string|null>>  $rows
     */
    public function map(array $rows): MappedHeader
    {
        $best = null;
        foreach (array_slice($rows, 0, self::HEADER_SCAN_ROWS) as $index => $row) {
            [$columns, $corrections] = $this->mapRow($row);
            if (count($columns) >= 2 && count($columns) >= count($best[1] ?? [])) {
                $best = [$index, $columns, $corrections];
            }
        }

        if ($best === null) {
            throw new ImportFileException("Couldn't find a header row. Make sure one row has column names such as Name, Barangay and RSBSA No.");
        }

        [$index, $columns, $corrections] = $best;
        if ($index > 0) {
            // Two-tier header: a group heading whose cell below is empty names that column itself ("Birthdate" merged down).
            [$groupColumns, $groupCorrections] = $this->mapRow($rows[$index - 1]);
            foreach ($groupColumns as $column => $field) {
                if (($rows[$index][$column] ?? null) === null && ! in_array($field, $columns, true)) {
                    $columns[$column] = $field;
                    $corrections = [...$corrections, ...array_values(array_filter($groupCorrections, fn ($c) => $c['field'] === $field))];
                }
            }
            ksort($columns);
        }

        return new MappedHeader($index, $columns, $corrections);
    }

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }

    public static function key(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($text));
    }

    /** @return array{0: array<int, string>, 1: list<array{header: string, field: string}>} */
    private function mapRow(array $row): array
    {
        $columns = [];
        $corrections = [];

        foreach ($row as $index => $cell) {
            if ($cell === null || SpreadsheetReader::isError($cell) || ($key = self::key($cell)) === '') {
                continue;
            }

            $field = $this->fieldFor($key);
            if ($field === null || in_array($field, $columns, true)) {
                continue;   // unknown heading, or that field is already mapped (first column wins)
            }

            $columns[$index] = $field;
            if ($key !== self::key(self::label($field))) {
                $corrections[] = ['header' => $cell, 'field' => $field];
            }
        }

        return [$columns, $corrections];
    }

    private function fieldFor(string $key): ?string
    {
        foreach (self::SYNONYMS as $field => $synonyms) {
            if (in_array($key, array_map(self::key(...), $synonyms), true)) {
                return $field;
            }
        }

        // Fuzzy: a near-miss of a longer synonym ("Barangy" → barangay).
        $best = null;
        $bestDistance = 3;
        foreach (self::SYNONYMS as $field => $synonyms) {
            foreach (array_map(self::key(...), $synonyms) as $synonym) {
                if (strlen($synonym) < 5 || strlen($key) < 4) {
                    continue;
                }
                $distance = levenshtein($key, $synonym);
                if ($distance < $bestDistance) {
                    [$best, $bestDistance] = [$field, $distance];
                }
            }
        }

        return $best;
    }
}
