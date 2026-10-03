<?php

namespace App\Imports;

use App\Exceptions\ImportFileException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Throwable;

/** Reads the first sheet of an .xlsx / .xls / .csv file into rows of trimmed strings. */
final class SpreadsheetReader
{
    /** Excel error values; a cell holding one is corrupt. */
    public const ERRORS = ['#NULL!', '#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A', '#GETTING_DATA', '#SPILL!', '#CALC!'];

    private const ERROR_PREFIX = "\0ERROR:";

    /** @return list<list<string|null>> */
    public function read(string $path, string $extension): array
    {
        try {
            $reader = IOFactory::createReader(match (strtolower($extension)) {
                'csv' => 'Csv',
                'xls' => 'Xls',
                default => 'Xlsx',
            });
            if ($reader instanceof Csv) {
                $reader->setDelimiter(self::csvDelimiter($path));
            }
            $sheet = $reader->load($path)->getSheet(0);
            $lastColumn = $sheet->getHighestDataColumn();
            $lastRow = $sheet->getHighestDataRow();
            $values = $sheet->rangeToArray("A1:{$lastColumn}{$lastRow}", null, true, false, false);
        } catch (Throwable) {
            throw new ImportFileException("This file couldn't be read as a spreadsheet.");
        }

        $width = Coordinate::columnIndexFromString($lastColumn);

        return array_map(fn (array $row) => array_slice(array_map($this->cell(...), array_pad($row, $width, null)), 0, $width), $values);
    }

    /**
     * PhpSpreadsheet guesses the delimiter from the first lines and can pick a space when a title line
     * ("OMAG Masterlist 2026") sits above the table; only comma, semicolon and tab are real CSV delimiters.
     */
    private static function csvDelimiter(string $path): string
    {
        $lines = array_slice(array_filter(file($path, FILE_IGNORE_NEW_LINES) ?: [], fn (string $line) => trim($line) !== ''), 0, 20);
        $widest = fn (string $delimiter) => max(array_map(fn (string $line) => substr_count($line, $delimiter), $lines) ?: [0]);

        return collect([',', ';', "\t"])->sortByDesc($widest)->first(fn (string $delimiter) => $widest($delimiter) > 0) ?? ',';
    }

    public static function isError(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::ERROR_PREFIX);
    }

    private function cell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            // Whole numbers keep every digit (RSBSA and phone numbers stored as numbers); decimals lose trailing zeros.
            return floor((float) $value) == $value && abs($value) < 1e15
                ? number_format((float) $value, 0, '', '')
                : rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        $text = trim((string) $value);
        if (in_array(strtoupper($text), self::ERRORS, true)) {
            return self::ERROR_PREFIX.$text;
        }

        return $text === '' ? null : $text;
    }
}
