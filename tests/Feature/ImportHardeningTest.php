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
            throw new UniqueConstraintViolationException('mysql', 'insert', [], new PDOException('Duplicate entry', 23000));
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
