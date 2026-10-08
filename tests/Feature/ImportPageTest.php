<?php

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
});

function masterlist(): UploadedFile
{
    return spreadsheet([
        ['Name', 'Birthdate', 'Brgy', 'RSBSA No.'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901'],
        ['Gloria Ramos', '1982-02-17', 'Poblacion', null],
        ['Ben Talawec', '1975-01-03', 'Atlantis', 'RSBSA-0902'],
    ]);
}

it('shows the Figma upload screen', function () {
    $this->actingAs($this->admin)->get('/import')->assertOk()
        ->assertSee('EXCEL IMPORT')
        ->assertSeeInOrder([
            'Excel Upload',
            'Scans incoming spreadsheets and will detect, align, and parse fields despite column shifts or label differences.',
            'drag &amp; drop .xlsx / .csv files here', 'or click to browse', 'Choose File',
            'Processing Feedback', 'Upload a spreadsheet to see how its rows will be read.',
            'Confirm &amp; Import',
        ], false)
        ->assertSee('data-confirm-disabled', false);
});

it('uploads a file and shows feedback and the preview', function () {
    $response = $this->actingAs($this->encoder)->post('/import', ['file' => masterlist()]);

    $batch = ImportBatch::sole();
    $response->assertRedirect(route('import.index', ['batch' => $batch]));

    $this->get(route('import.index', ['batch' => $batch]))->assertOk()
        ->assertSeeInOrder([
            '2 rows matched automatically (Name, Birthdate, Barangay, RSBSA No.)',
            "Column shift auto-corrected: 'Brgy' -&gt; mapped to 'Barangay'",
            '1 row excluded — see the reasons in the preview',
        ], false)
        ->assertSeeInOrder(['Preview', 'masterlist.xlsx', 'Pablo Ramos', 'RSBSA-0901', 'Ready', 'Gloria Ramos', 'Ready', 'Ben Talawec', 'Excluded', "Unknown barangay 'Atlantis'"])
        ->assertSee('Discard')
        ->assertDontSee('data-confirm-disabled', false);
});

it('lists rows that need attention first in a long preview', function () {
    $rows = [['Name', 'Birthdate', 'Barangay', 'RSBSA No.']];
    foreach (range(1, 205) as $i) {
        $rows[] = ["Farmer{$i} Ramos", '1980-05-10', 'Poblacion', "RSBSA-9{$i}"];
    }
    $rows[] = ['Ben Talawec', '1975-01-03', 'Atlantis', 'RSBSA-0902'];

    $this->actingAs($this->encoder)->post('/import', ['file' => spreadsheet($rows)]);

    $this->get('/import')->assertSeeInOrder(['Ben Talawec', "Unknown barangay 'Atlantis'", 'Farmer1 Ramos'])
        ->assertSee('rows that need attention are listed first');
});

it('opens the latest staged batch of the user by default', function () {
    $this->actingAs($this->encoder)->post('/import', ['file' => masterlist()]);

    $this->get('/import')->assertSee('Pablo Ramos')->assertSee('Preview');
    $this->actingAs($this->admin)->get('/import')->assertDontSee('Pablo Ramos');
});

it('shows the upload error on the card', function () {
    $pdf = UploadedFile::fake()->create('masterlist.pdf', 10, 'application/pdf');

    $this->actingAs($this->encoder)->from('/import')->followingRedirects()->post('/import', ['file' => $pdf])
        ->assertOk()->assertSee('<span class="mt-[8px] text-[13px] font-semibold leading-[17px] text-danger" role="alert">Upload an .xlsx, .xls or .csv file.</span>', false);

    expect(ImportBatch::count())->toBe(0);
});

it('asks for a file when none was chosen', function () {
    $this->actingAs($this->encoder)->from('/import')->post('/import')
        ->assertSessionHasErrors(['file' => 'Choose a spreadsheet to upload.']);
});

it('turns a file PHP refused into the size message', function () {
    $refused = new UploadedFile(spreadsheet([['Name']])->getRealPath(), 'big.xlsx', null, UPLOAD_ERR_INI_SIZE, true);

    $this->actingAs($this->encoder)->from('/import')->post('/import', ['file' => $refused])
        ->assertSessionHasErrors(['file' => 'The file is larger than 25 MB.']);
});

it('turns a request too large for PHP into the size message', function () {
    $this->actingAs($this->encoder)
        ->call('POST', '/import', server: ['CONTENT_LENGTH' => (string) (1024 * 1024 * 1024)])
        ->assertRedirect(route('import.index', ['too_large' => 1]));

    $this->get(route('import.index', ['too_large' => 1]))->assertSee('The file is larger than 25 MB.');
});

it('discards a staged batch', function () {
    $this->actingAs($this->encoder)->post('/import', ['file' => masterlist()]);
    $batch = ImportBatch::sole();

    $this->post(route('import.discard', $batch))->assertRedirect(route('import.index'))->assertSessionHas('status', 'Import of masterlist.xlsx discarded.');

    expect($batch->fresh()->status)->toBe(ImportBatch::DISCARDED)
        ->and(AuditLog::where('action', 'Discarded Excel Import')->sole()->record_label)->toBe('masterlist.xlsx');
    $this->get('/import')->assertDontSee('Pablo Ramos');
});

it('keeps batches private to their uploader', function () {
    $this->actingAs($this->encoder)->post('/import', ['file' => masterlist()]);
    $batch = ImportBatch::sole();

    $this->actingAs($this->admin)->get(route('import.index', ['batch' => $batch]))->assertNotFound();
    $this->post(route('import.discard', $batch))->assertNotFound();
    expect($batch->fresh()->status)->toBe(ImportBatch::STAGED);
});

it('forbids agri techs', function () {
    $this->actingAs($this->agritech)->get('/import')->assertForbidden();
    $this->post('/import', ['file' => masterlist()])->assertForbidden();
});
