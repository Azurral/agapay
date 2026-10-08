<?php

use App\Exceptions\ImportFileException;
use App\Models\Beneficiary;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\ExcelImportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

function stageHardening(array $rows): ImportBatch
{
    return app(ExcelImportService::class)->stage(spreadsheet($rows), User::where('username', 'Encoder_03')->sole());
}

it('reports a different RSBSA number for an existing profile', function () {
    $juan = Beneficiary::where('rsbsa_number', 'RSBSA-0231')->sole();
    $batch = stageHardening([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Juan Dela Cruz', $juan->birthdate->toDateString(), 'Poblacion', 'RSBSA-9999']]);

    expect($batch->rows()->sole())->status->toBe('unreadable')
        ->issues->toBe(['Juan Dela Cruz already has RSBSA No. RSBSA-0231 in AGAPAY']);
});

it('turns a clash during confirm into a row message', function () {
    $batch = stageHardening([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);
    // Someone registers the same number between the check and the insert.
    Beneficiary::creating(function (Beneficiary $b) {
        if ($b->rsbsa_number === 'RSBSA-0901') {
            throw new UniqueConstraintViolationException('mysql', 'insert', [], new PDOException("Duplicate entry 'RSBSA-0901' for key 'beneficiaries_rsbsa_number_unique'", 23000));
        }
    });

    expect(fn () => app(ExcelImportService::class)->confirm($batch, $this->encoder))
        ->toThrow(ImportFileException::class, 'Row 2: RSBSA No. RSBSA-0901 was registered by someone else meanwhile.')
        ->and($batch->fresh()->status)->toBe(ImportBatch::STAGED);
});

it('deletes the uploaded file after confirm or discard', function () {
    $confirmed = stageHardening([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);
    $discarded = stageHardening([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Ben Talawec', '1975-01-03', 'Samoki', 'RSBSA-0902']]);
    expect(Storage::disk('local')->exists($confirmed->stored_path))->toBeTrue();

    app(ExcelImportService::class)->confirm($confirmed, $this->encoder);
    app(ExcelImportService::class)->discard($discarded, $this->encoder);

    expect(Storage::disk('local')->exists($confirmed->stored_path))->toBeFalse()
        ->and(Storage::disk('local')->exists($discarded->stored_path))->toBeFalse()
        ->and($confirmed->rows()->count())->toBe(1);
});

it('reads an xls file saved with an xlsx name', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);
    $path = tempnam(sys_get_temp_dir(), 'agapay').'.xls';
    IOFactory::createWriter($book, 'Xls')->save($path);

    $batch = app(ExcelImportService::class)->stage(new UploadedFile($path, 'masterlist.xlsx', null, null, true), $this->encoder);

    expect($batch->rows()->sole()->data['first_name'])->toBe('Pablo');
});

it('tells the user how to recover from a confirm error', function () {
    $batch = stageHardening([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);
    Beneficiary::factory()->create(['rsbsa_number' => 'RSBSA-0901']);

    $this->actingAs($this->encoder)->followingRedirects()->post(route('import.confirm', $batch))
        ->assertSee('Row 2: RSBSA No. RSBSA-0901 is already used by')
        ->assertSee('Discard this upload, fix the file and upload it again.');
});

it('upper-cases RSBSA numbers an import records', function () {
    $federico = Beneficiary::where('first_name', 'Federico')->sole();
    $batch = stageHardening([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Federico Wasing', $federico->birthdate->toDateString(), 'Bayyo', 'rsbsa-0777']]);

    app(ExcelImportService::class)->confirm($batch, $this->encoder);

    expect($federico->fresh()->rsbsa_number)->toBe('RSBSA-0777');
});

it('clears week-old unconfirmed and discarded uploads when someone uploads again', function () {
    $old = stageHardening([['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', '1980-05-10', 'Poblacion']]);
    $discarded = stageHardening([['Name', 'Birthdate', 'Barangay'], ['Ben Talawec', '1975-01-03', 'Samoki']]);
    app(ExcelImportService::class)->discard($discarded, $this->encoder);
    $recent = stageHardening([['Name', 'Birthdate', 'Barangay'], ['Rita Bangsoy', '1977-07-07', 'Samoki']]);
    ImportBatch::whereKey([$old->id, $discarded->id])->update(['created_at' => now()->subDays(8)]);
    Storage::disk('local')->put('imports/stray-leftover.xlsx', 'x');
    touch(Storage::disk('local')->path('imports/stray-leftover.xlsx'), now()->subDays(8)->getTimestamp());

    stageHardening([['Name', 'Birthdate', 'Barangay'], ['Joy Tayag', '1979-09-09', 'Samoki']]);

    expect($old->fresh())->status->toBe(ImportBatch::DISCARDED)
        ->and($old->rows()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($old->stored_path))->toBeFalse()
        ->and($discarded->rows()->count())->toBe(0)
        ->and(Storage::disk('local')->exists('imports/stray-leftover.xlsx'))->toBeFalse()
        ->and($recent->fresh()->status)->toBe(ImportBatch::STAGED)
        ->and($recent->rows()->count())->toBe(1)
        ->and(Storage::disk('local')->exists($recent->stored_path))->toBeTrue();
});
