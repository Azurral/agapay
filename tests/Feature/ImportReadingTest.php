<?php

use App\Exceptions\ImportFileException;
use App\Imports\ColumnMapper;
use App\Imports\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

function readSheet(array $rows, string $type = 'xlsx'): array
{
    $file = spreadsheet($rows, $type);

    return app(SpreadsheetReader::class)->read($file->getPathname(), $type);
}

it('reads xlsx and csv rows as strings', function (string $type) {
    expect(readSheet([['Name', 'Barangay'], ['Juan Dela Cruz', 'Poblacion']], $type))
        ->toBe([['Name', 'Barangay'], ['Juan Dela Cruz', 'Poblacion']]);
})->with(['xlsx', 'csv']);

it('reads csv files whose title line has no commas', function (string $delimiter) {
    $path = tempnam(sys_get_temp_dir(), 'agapay').'.csv';
    $line = fn (array $cells) => implode($delimiter, $cells)."\n";
    file_put_contents($path, "OMAG Masterlist 2026\n\n"
        .$line(['Farmer Name', 'Date of Birth', 'Brgy.', 'RSBSA #', 'Contact'])
        .$line(['Pablo Ramos', '05/10/1980', 'poblacion', 'RSBSA-0901', '9171234567']));

    expect(app(SpreadsheetReader::class)->read($path, 'csv'))->toBe([
        ['OMAG Masterlist 2026', null, null, null, null],
        [null, null, null, null, null],
        ['Farmer Name', 'Date of Birth', 'Brgy.', 'RSBSA #', 'Contact'],
        ['Pablo Ramos', '05/10/1980', 'poblacion', 'RSBSA-0901', '9171234567'],
    ]);
})->with([',', ';', "\t"]);

it('reads csv files saved by Excel in the Windows encoding', function () {
    $path = tempnam(sys_get_temp_dir(), 'agapay').'.csv';
    file_put_contents($path, "Name,Barangay\nJuan Pe\xF1a,Santo Ni\xF1o\n");

    expect(app(SpreadsheetReader::class)->read($path, 'csv'))->toBe([['Name', 'Barangay'], ['Juan Peña', 'Santo Niño']]);
});

it('keeps reading when a formula links to another workbook', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([['Name', 'Birthdate'], ['Juan Cruz', null], ['Ana Cruz', '1980-01-01']]);
    $book->getActiveSheet()->setCellValueExplicit('B2', "='C:\\x\\[book.xlsx]Sheet1'!A1", DataType::TYPE_FORMULA);
    $path = tempnam(sys_get_temp_dir(), 'agapay').'.xlsx';
    (new XlsxWriter($book))->setPreCalculateFormulas(false)->save($path);

    $rows = app(SpreadsheetReader::class)->read($path, 'xlsx');

    expect($rows[1][0])->toBe('Juan Cruz')
        ->and(SpreadsheetReader::isError($rows[1][1]))->toBeTrue()
        ->and($rows[2])->toBe(['Ana Cruz', '1980-01-01']);
});

it('counts the rows of a sheet without reading it', function (string $type) {
    $file = spreadsheet([['Name', 'Barangay'], ['Juan', 'Poblacion'], ['Ana', 'Samoki']], $type);

    expect(app(SpreadsheetReader::class)->rowCount($file->getPathname(), $type))->toBe(3);
})->with(['xlsx', 'csv']);

it('reads a two-tier header with group headings above the column names', function () {
    $header = app(ColumnMapper::class)->map([
        ['OMAG Bontoc Masterlist'],
        ['Name', null, null, 'Birthdate', 'Address', null, 'RSBSA No.'],
        ['Last Name', 'First Name', 'Middle Name', null, 'Barangay', 'Purok', null],
        ['Dela Cruz', 'Juan', 'A', '1980-01-01', 'Poblacion', 'Purok 3', 'RSBSA-1'],
    ]);

    expect($header->headerIndex)->toBe(2)
        ->and($header->columns)->toBe([0 => 'last_name', 1 => 'first_name', 2 => 'middle_name', 3 => 'birthdate', 4 => 'barangay', 5 => 'address', 6 => 'rsbsa_number']);
});

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
