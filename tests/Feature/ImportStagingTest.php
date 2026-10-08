<?php

use App\Exceptions\ImportFileException;
use App\Imports\MappedHeader;
use App\Imports\RowNormalizer;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Intervention;
use App\Models\User;
use App\Services\ExcelImportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

function stageRows(array $rows, string $type = 'xlsx'): ImportBatch
{
    return app(ExcelImportService::class)->stage(spreadsheet($rows, $type), User::where('username', 'Encoder_03')->sole());
}

/** The staged row for a 1-based sheet row number. */
function stagedRow(ImportBatch $batch, int $rowNumber): ImportRow
{
    return $batch->rows()->where('row_number', $rowNumber)->sole();
}

it('stages a clean masterlist', function () {
    $batch = stageRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901'],
        ['Gloria Ramos', '1982-02-17', 'Poblacion', null],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'RSBSA-0902'],
    ]);

    expect($batch)->status->toBe('staged')->original_name->toBe('masterlist.xlsx')
        ->and($batch->counts)->toMatchArray(['ready' => 3, 'flagged' => 0, 'update' => 0, 'duplicate' => 0, 'unreadable' => 0])
        ->and($batch->feedback)->toBe([
            ['ok' => true, 'text' => '3 rows matched automatically (Name, Birthdate, Barangay, RSBSA No.)'],
        ])
        ->and(stagedRow($batch, 2)->data)->toMatchArray([
            'first_name' => 'Pablo', 'last_name' => 'Ramos', 'birthdate' => '1980-05-10',
            'barangay_id' => Barangay::where('name', 'Poblacion')->value('id'), 'sitio' => 'Barangay Poblacion', 'rsbsa_number' => 'RSBSA-0901',
        ])
        ->and(stagedRow($batch, 3))->status->toBe('ready')->data->toMatchArray(['rsbsa_number' => null])
        ->and(Beneficiary::where('last_name', 'Ramos')->exists())->toBeFalse()   // nothing imported yet
        ->and(AuditLog::where('action', 'Uploaded Excel File')->value('record_label'))->toBe('masterlist.xlsx');
});

it('reads csv files too', function () {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', '1980-05-10', 'Poblacion']], 'csv');

    expect($batch->counts['ready'])->toBe(1);
});

it('reports column shift corrections', function () {
    $batch = stageRows([['Farmer Name', 'Birthdate', 'Brgy'], ['Pablo Ramos', '1980-05-10', 'Poblacion']]);

    expect(collect($batch->feedback)->pluck('text')->all())
        ->toContain("Column shift auto-corrected: 'Farmer Name' -> mapped to 'Name'", "Column shift auto-corrected: 'Brgy' -> mapped to 'Barangay'");
});

it('splits full names, keeping compound surnames together', function (string $name, array $parts) {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay'], [$name, '1980-05-10', 'Poblacion']]);

    expect(array_values(array_intersect_key(stagedRow($batch, 2)->data, array_flip(['first_name', 'middle_name', 'last_name']))))->toBe($parts);
})->with([
    ['Dela Cruz, Pedro Abenoja', ['Pedro', 'Abenoja', 'Dela Cruz']],
    ['Pedro Abenoja Ramos', ['Pedro', 'Abenoja', 'Ramos']],
    ['Pedro Dela Cruz', ['Pedro', null, 'Dela Cruz']],
    ['Ana Maria de los Santos', ['Ana', 'Maria', 'de los Santos']],
    ['  pedro   RAMOS ', ['pedro', null, 'RAMOS']],
]);

it('uses separate first, middle and last name columns when present', function () {
    $batch = stageRows([['Last Name', 'First Name', 'MI', 'DOB', 'Barangay'], ['Ramos', 'Pablo', 'T.', '1980-05-10', 'Poblacion']]);

    expect(stagedRow($batch, 2)->data)->toMatchArray(['first_name' => 'Pablo', 'middle_name' => 'T.', 'last_name' => 'Ramos']);
});

it('reads birthdates in office formats', function (string|int $cell) {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', $cell, 'Poblacion']]);

    expect(stagedRow($batch, 2)->data['birthdate'])->toBe('1982-02-17');
})->with([29999, '1982-02-17', '02/17/1982', '17/02/1982', 'Feb 17, 1982', 'February 17, 1982']);

it('excludes unreadable birthdates', function () {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', '31/31/1990', 'Poblacion']]);

    expect(stagedRow($batch, 2))->status->toBe('unreadable')->issues->toBe(["Unreadable birthdate '31/31/1990'"]);
});

it('excludes a birthdate that is only a year', function () {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', 1975, 'Poblacion']]);

    expect(stagedRow($batch, 2))->status->toBe('unreadable')->issues->toBe(["Unreadable birthdate '1975' (year only)"]);
});

it('excludes values longer than their field', function () {
    $batch = stageRows([
        ['First Name', 'Last Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Address'],
        [str_repeat('a', 101), 'Ramos', '1980-05-10', 'Poblacion', str_repeat('9', 51), str_repeat('b', 101)],
        [str_repeat('a', 100), 'Ramos', '1980-05-10', 'Poblacion', str_repeat('9', 50), str_repeat('b', 100)],
    ]);

    expect(stagedRow($batch, 2))->status->toBe('unreadable')->issues->toBe([
        'First Name is longer than 100 characters', 'Address is longer than 100 characters', 'RSBSA No. is longer than 50 characters',
    ])->and(stagedRow($batch, 3)->status)->toBe('ready');
});

it('notes why an intervention is skipped when no cycle exists', function () {
    $normalizer = new RowNormalizer(Barangay::pluck('name', 'id'), Intervention::all(), collect(), null);
    $header = new MappedHeader(0, [0 => 'full_name', 1 => 'birthdate', 2 => 'barangay', 3 => 'intervention'], []);

    $row = $normalizer->normalize(['Pablo Ramos', '1980-05-10', 'Poblacion', 'DA - Certified Rice Seeds'], $header, 2);

    expect($row['issues'])->toBe(['Intervention skipped: no distribution cycle exists yet'])
        ->and($row['data']['intervention_id'])->toBeNull();
});

it('fuzzy-matches barangays', function (string $cell, ?string $barangay) {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', '1980-05-10', $cell]]);
    $row = stagedRow($batch, 2);

    $barangay
        ? expect($row->data['barangay'])->toBe($barangay)
        : expect($row)->status->toBe('unreadable')->issues->toBe(["Unknown barangay '{$cell}'"]);
})->with([
    ['Bontoc-Ili', 'Bontoc Ili'],
    ['brgy. samoki', 'Samoki'],
    ['Guinaang', 'Guina-ang'],
    ['POBLACION', 'Poblacion'],
    ['Atlantis', null],
]);

it('excludes corrupted, nameless and under-age rows with reasons', function () {
    $batch = stageRows([
        ['Name', 'Birthdate', 'Barangay'],
        ['Pablo Ramos', '1980-05-10', ['error' => '#REF!']],
        [null, '1980-05-10', 'Poblacion'],
        ['Young Farmer', today()->subYears(12)->toDateString(), 'Poblacion'],
        ['No Birthdate', null, 'Poblacion'],
    ]);

    expect(stagedRow($batch, 2)->issues)->toBe(['Corrupted cell in Barangay'])
        ->and(stagedRow($batch, 3)->issues)->toBe(['Missing name'])
        ->and(stagedRow($batch, 4)->issues)->toBe(['Under 18 (born '.today()->subYears(12)->format('M j, Y').')'])
        ->and(stagedRow($batch, 5)->issues)->toBe(['Missing birthdate'])
        ->and($batch->counts['unreadable'])->toBe(4)
        ->and(collect($batch->feedback)->pluck('text'))->toContain('4 rows excluded — see the reasons in the preview');
});

it('skips blank rows inside the data', function () {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', '1980-05-10', 'Poblacion'], [], ['Ben Talawec', '1975-01-03', 'Samoki']]);

    expect($batch->rows()->count())->toBe(2)->and($batch->rows()->pluck('row_number')->all())->toBe([2, 4]);
});

it('dedupes against AGAPAY and within the file', function () {
    $juan = Beneficiary::where(['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])->sole();
    $batch = stageRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.'],
        ['JUAN  dela cruz', $juan->birthdate->toDateString(), 'Poblacion', null],      // already in AGAPAY
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901'],                     // repeated in the file
        ['Pedro Ibay', '1979-03-03', 'Samoki', 'rsbsa-0198'],                         // Maria Santos's number
        ['Juan Dela Cruz', '1960-01-01', 'Samoki', ' RSBSA-0231 '],                   // Juan's own number
    ]);

    expect(stagedRow($batch, 2)->status)->toBe('duplicate')
        ->and(stagedRow($batch, 3)->status)->toBe('ready')
        ->and(stagedRow($batch, 4)->status)->toBe('duplicate')
        ->and(stagedRow($batch, 5))->status->toBe('unreadable')->issues->toBe(['RSBSA No. rsbsa-0198 belongs to Maria Santos'])
        ->and(stagedRow($batch, 6)->status)->toBe('duplicate')
        ->and(collect($batch->feedback)->pluck('text'))->toContain('3 duplicates skipped (already in AGAPAY or repeated in the file)');
});

it('excludes a second person who carries the same RSBSA No. in the file', function () {
    $batch = stageRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901'],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'rsbsa-0901'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', null],
    ]);

    expect(stagedRow($batch, 3))->status->toBe('unreadable')->issues->toBe(['RSBSA No. rsbsa-0901 is also on row 2 (Pablo Ramos)'])
        ->and(stagedRow($batch, 4))->status->toBe('duplicate')->issues->toBe(['Same person as row 2']);
});

it('notes what a duplicate repeats', function () {
    $juan = Beneficiary::where('rsbsa_number', 'RSBSA-0231')->sole();
    $batch = stageRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Juan Dela Cruz', $juan->birthdate->toDateString(), 'Poblacion', null]]);

    expect(stagedRow($batch, 2)->issues)->toBe(['Already in AGAPAY']);
});

it('skips a DA program for a farmer without an RSBSA number but keeps the farmer', function () {
    $batch = stageRows([
        ['Name', 'Birthdate', 'Barangay', 'Intervention'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'DA - Certified Rice Seeds'],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'LGU - Emergency Seedlings'],
    ]);

    expect(stagedRow($batch, 2))->status->toBe('ready')
        ->issues->toBe(["Intervention skipped: no RSBSA No. (DA programs need one; LGU programs don't)"])
        ->and(stagedRow($batch, 2)->data['intervention_id'])->toBeNull()
        ->and(stagedRow($batch, 3)->data['intervention_id'])->toBe(program('lgu', 'Emergency Seedlings')->id);
});

it('excludes rows whose RSBSA No. belongs to an archived profile', function () {
    Beneficiary::where('rsbsa_number', 'RSBSA-0198')->sole()->delete();

    $batch = stageRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Maria Santos', '1970-01-01', 'Samoki', 'RSBSA-0198']]);

    expect(stagedRow($batch, 2))->status->toBe('unreadable')
        ->issues->toBe(['RSBSA No. RSBSA-0198 belongs to the archived profile of Maria Santos']);
});

it('plans RSBSA updates for existing profiles without a number', function () {
    $federico = Beneficiary::where('first_name', 'Federico')->sole();
    $batch = stageRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Federico Wasing', $federico->birthdate->toDateString(), 'Bayyo', 'RSBSA-0777']]);

    expect(stagedRow($batch, 2))->status->toBe('update')
        ->and(stagedRow($batch, 2)->data['beneficiary_id'])->toBe($federico->id)
        ->and(collect($batch->feedback)->pluck('text'))->toContain('1 existing profile will receive its RSBSA No.');
});

it('resolves optional intervention columns', function () {
    $batch = stageRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Intervention', 'Qty', 'Batch', 'Date Distributed'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901', 'DA - Certified Rice Seeds', 2, '2026-Q3', '2026-07-20'],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'RSBSA-0902', 'PAFF', null, null, null],
        ['Rita Bangsoy', '1977-07-07', 'Samoki', 'RSBSA-0903', 'Magic Beans', null, null, null],
        ['Lito Fagyan', '1978-08-08', 'Samoki', 'RSBSA-0904', 'Complete Fertilizer', null, null, null],
        ['Joy Tayag', '1979-09-09', 'Samoki', 'RSBSA-0905', 'LGU Emergency Seedlings', null, '2026-Q9', null],
    ]);

    expect(stagedRow($batch, 2)->data)->toMatchArray([
        'intervention_id' => program('da', 'Certified Rice Seeds')->id, 'quantity' => '2',
        'cycle_id' => DistributionCycle::where('code', '2026-Q3')->value('id'), 'date_distributed' => '2026-07-20',
    ])
        ->and(stagedRow($batch, 3)->data['intervention_id'])->toBe(program('da', 'PAFF')->id)
        ->and(stagedRow($batch, 4))->status->toBe('ready')->issues->toBe(["Intervention skipped: unknown program 'Magic Beans'"])
        ->and(stagedRow($batch, 5)->issues)->toBe(["Intervention skipped: 'Complete Fertilizer' is both a DA and an LGU program — write DA or LGU before it"])
        ->and(stagedRow($batch, 6)->issues)->toBe(["Intervention skipped: unknown cycle '2026-Q9'"]);
});

it('fixes leading zeros of contact numbers and ignores unusable ones', function () {
    $batch = stageRows([['Name', 'Birthdate', 'Barangay', 'Contact'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 9171234567], ['Ben Talawec', '1975-01-03', 'Samoki', 'n/a']]);

    expect(stagedRow($batch, 2)->data['contact_number'])->toBe('09171234567')
        ->and(stagedRow($batch, 3))->status->toBe('ready')->issues->toBe(["Contact number 'n/a' ignored"])
        ->and(stagedRow($batch, 3)->data['contact_number'])->toBeNull();
});

it('refuses bad files', function (Closure $file, string $message) {
    expect(fn () => app(ExcelImportService::class)->stage($file(), User::where('username', 'Encoder_03')->sole()))
        ->toThrow(ImportFileException::class, $message);
})->with([
    'wrong type' => [fn () => UploadedFile::fake()->create('notes.pdf', 10), 'Upload an .xlsx, .xls or .csv file.'],
    'too large' => [fn () => UploadedFile::fake()->create('huge.xlsx', 26 * 1024), 'The file is larger than 25 MB.'],
    'header only' => [fn () => spreadsheet([['Name', 'Birthdate', 'Barangay']]), 'The sheet has no data rows under its header.'],
    'missing columns' => [fn () => spreadsheet([['Name', 'RSBSA No.'], ['Pablo Ramos', 'RSBSA-1']]), 'The sheet needs Name (or First Name and Last Name), Birthdate and Barangay columns.'],
]);

it('refuses sheets over the row limit', function () {
    config(['agapay.import.max_rows' => 2]);

    expect(fn () => stageRows([['Name', 'Birthdate', 'Barangay'], ['A B', '1980-01-01', 'Poblacion'], ['C D', '1980-01-01', 'Poblacion'], ['E F', '1980-01-01', 'Poblacion']]))
        ->toThrow(ImportFileException::class, 'The sheet has more than 2 rows. Split it into smaller files.');
});
