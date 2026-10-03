<?php

use App\Exceptions\ImportFileException;
use App\Imports\ColumnMapper;
use App\Imports\SpreadsheetReader;

function readSheet(array $rows, string $type = 'xlsx'): array
{
    $file = spreadsheet($rows, $type);

    return app(SpreadsheetReader::class)->read($file->getPathname(), $type);
}

it('reads xlsx and csv rows as strings', function (string $type) {
    expect(readSheet([['Name', 'Barangay'], ['Juan Dela Cruz', 'Poblacion']], $type))
        ->toBe([['Name', 'Barangay'], ['Juan Dela Cruz', 'Poblacion']]);
})->with(['xlsx', 'csv']);

it('keeps numeric RSBSA and contact digits', function () {
    $rows = readSheet([['RSBSA No.', 'Contact', 'Qty', 'Area'], [171234567890, 9171234567, 3, 2.5]]);

    expect($rows[1])->toBe(['171234567890', '9171234567', '3', '2.5']);
});

it('marks error cells', function () {
    $rows = readSheet([['Name', 'Barangay'], [['error' => '#REF!'], 'Poblacion']]);

    expect(SpreadsheetReader::isError($rows[1][0]))->toBeTrue()
        ->and(SpreadsheetReader::isError($rows[1][1]))->toBeFalse()
        ->and(SpreadsheetReader::isError(null))->toBeFalse();
});

it('finds the header below title rows and after blank columns', function () {
    $rows = readSheet([
        ['AGAPAY Masterlist 2026'],
        [],
        [null, null, 'Name', 'Brgy.', 'RSBSA #'],
        [null, null, 'Juan Dela Cruz', 'Poblacion', 'RSBSA-0231'],
    ]);

    $header = app(ColumnMapper::class)->map($rows);

    expect($header->headerIndex)->toBe(2)
        ->and($header->columns)->toBe([2 => 'full_name', 3 => 'barangay', 4 => 'rsbsa_number']);
});

it('maps synonyms and fuzzy headers', function (string $heading, ?string $field) {
    $header = app(ColumnMapper::class)->map([[$heading, 'Barangay', 'Name']]);

    expect($header->columns[0] ?? null)->toBe($field);
})->with([
    ['Brgy.', 'barangay'],
    ['RSBSA #', 'rsbsa_number'],
    ['Date of Birth', 'birthdate'],
    ['Barangy', 'barangay'],
    ['Farmer Name', 'full_name'],
    ['Cellphone No.', 'contact_number'],
    ['Surname', 'last_name'],
    ['Notes', null],
]);

it('maps each field once', function () {
    $header = app(ColumnMapper::class)->map([['Name', 'Barangay', 'Brgy']]);

    expect($header->columns)->toBe([0 => 'full_name', 1 => 'barangay']);
});

it('reports header corrections', function () {
    $header = app(ColumnMapper::class)->map([['Name', 'Brgy', 'RSBSA No.']]);

    expect($header->corrections)->toBe([['header' => 'Brgy', 'field' => 'barangay']])
        ->and(ColumnMapper::label('barangay'))->toBe('Barangay')
        ->and(ColumnMapper::label('full_name'))->toBe('Name');
});

it('refuses a sheet without a header row', function () {
    expect(fn () => app(ColumnMapper::class)->map([['hello', 'world'], ['1', '2']]))
        ->toThrow(ImportFileException::class, "Couldn't find a header row. Make sure one row has column names such as Name, Barangay and RSBSA No.");
});

it('refuses a file that is not a spreadsheet', function () {
    $path = tempnam(sys_get_temp_dir(), 'agapay').'.xlsx';
    file_put_contents($path, '%PDF-1.4 not a workbook');

    expect(fn () => app(SpreadsheetReader::class)->read($path, 'xlsx'))
        ->toThrow(ImportFileException::class, "This file couldn't be read as a spreadsheet.");
});
