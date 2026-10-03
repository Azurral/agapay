<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\ImportBatch;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->admin = User::where('username', 'Admin_01')->sole();
});

/** Uploads the rows as the encoder and returns the staged batch. */
function uploadRows(array $rows): ImportBatch
{
    test()->actingAs(User::where('username', 'Encoder_03')->sole())->post('/import', ['file' => spreadsheet($rows)]);

    return ImportBatch::latest('id')->firstOrFail();
}

it('imports ready and flagged rows and updates RSBSA numbers', function () {
    $federico = Beneficiary::where('first_name', 'Federico')->sole();
    $batch = uploadRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Address', 'Contact'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901', 'Purok 2', 9171234567],
        ['Gloria Ramos', '1982-02-17', 'Poblacion', null, 'Purok 2', null],
        ['Federico Wasing', $federico->birthdate->toDateString(), 'Bayyo', 'RSBSA-0777', null, null],
        ['Juan Dela Cruz', Beneficiary::where('rsbsa_number', 'RSBSA-0231')->value('birthdate')->toDateString(), 'Poblacion', null, null, null],
    ]);
    $before = Beneficiary::count();

    $this->post(route('import.confirm', $batch))->assertRedirect(route('import.index'))
        ->assertSessionHas('status', 'Imported 2 new profiles, updated 1, added 0 intervention records.');

    $pablo = Beneficiary::where('rsbsa_number', 'RSBSA-0901')->sole();
    $gloria = Beneficiary::where(['first_name' => 'Gloria', 'last_name' => 'Ramos'])->sole();
    expect(Beneficiary::count())->toBe($before + 2)
        ->and($pablo)->rsbsa_status->toBe(Beneficiary::RSBSA_REGISTERED)->source->toBe(Beneficiary::SOURCE_IMPORT)
        ->contact_number->toBe('09171234567')->address->toBe('Purok 2')->created_by->toBe($this->encoder->id)
        ->and($gloria)->rsbsa_status->toBe(Beneficiary::RSBSA_ENDORSED)->encoding_issue->toBe('Missing RSBSA Number')->rsbsa_number->toBeNull()
        ->and($federico->fresh())->rsbsa_number->toBe('RSBSA-0777')->rsbsa_status->toBe(Beneficiary::RSBSA_REGISTERED)->encoding_issue->toBeNull()
        ->and($batch->fresh())->status->toBe(ImportBatch::IMPORTED)->imported_at->not->toBeNull()
        ->and($batch->rows()->where('row_number', 2)->value('beneficiary_id'))->toBe($pablo->id);

    $this->get('/dashboard')->assertSee('Gloria Ramos')->assertDontSee('Federico Wasing');
});

it('groups imported people into households', function () {
    $batch = uploadRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Address'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901', 'Purok 2'],
        ['Gloria Ramos', '1982-02-17', 'Poblacion', 'RSBSA-0902', 'purok 2, Poblacion'],
    ]);

    $this->post(route('import.confirm', $batch));

    $pablo = Beneficiary::where('rsbsa_number', 'RSBSA-0901')->sole();
    expect($pablo->household_id)->not->toBeNull()
        ->and(Beneficiary::where('rsbsa_number', 'RSBSA-0902')->value('household_id'))->toBe($pablo->household_id);
});

it('creates intervention records and deducts stock for distributed rows', function () {
    $rice = InventoryItem::where('name', 'Certified Rice Seeds')->sole();
    $balance = $rice->balance();
    $batch = uploadRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Intervention', 'Qty', 'Date Distributed'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901', 'DA - Certified Rice Seeds', 2, '2026-07-20'],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'RSBSA-0902', 'DA - Certified Rice Seeds', null, null],
    ]);

    $this->post(route('import.confirm', $batch))
        ->assertSessionHas('status', 'Imported 2 new profiles, updated 0, added 2 intervention records.');

    $claimed = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('rsbsa_number', 'RSBSA-0901'))->sole();
    $assigned = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('rsbsa_number', 'RSBSA-0902'))->sole();
    expect($claimed)->claim_status->toBe(InterventionRecord::CLAIM_CLAIMED)->date_distributed->toDateString()->toBe('2026-07-20')
        ->and($assigned)->claim_status->toBe(InterventionRecord::CLAIM_UNCLAIMED)->validation_status->toBe(InterventionRecord::VALIDATION_PENDING)
        ->and($rice->fresh()->balance())->toEqual($balance - 2);
});

it('rolls everything back when a row breaks a distribution rule', function () {
    $before = [Beneficiary::count(), InterventionRecord::count()];
    $batch = uploadRows([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Intervention', 'Qty', 'Date Distributed'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901', null, null, null],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'RSBSA-0902', 'DA - Certified Rice Seeds', 9999, '2026-07-20'],
    ]);

    $this->followingRedirects()->post(route('import.confirm', $batch))
        ->assertSee('Row 3: Not enough stock')->assertSee('Confirm &amp; Import', false);

    expect([Beneficiary::count(), InterventionRecord::count()])->toBe($before)
        ->and($batch->fresh()->status)->toBe(ImportBatch::STAGED);
});

it('imports a batch only once', function () {
    $batch = uploadRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);

    $this->post(route('import.confirm', $batch));
    $count = Beneficiary::count();
    $this->post(route('import.confirm', $batch))->assertSessionHasErrors(['confirm' => 'This import was already confirmed.']);

    expect(Beneficiary::count())->toBe($count);
});

it('skips rows that were registered after staging', function () {
    $batch = uploadRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', null]]);
    Beneficiary::factory()->create(['first_name' => 'Pablo', 'last_name' => 'Ramos', 'birthdate' => '1980-05-10', 'barangay_id' => $batch->rows()->value('data')['barangay_id']]);
    $count = Beneficiary::count();

    $this->post(route('import.confirm', $batch))->assertSessionHas('status', 'Imported 0 new profiles, updated 0, added 0 intervention records.');

    expect(Beneficiary::count())->toBe($count)->and($batch->rows()->value('status'))->toBe('duplicate');
});

it('refuses an RSBSA No. that was taken after staging', function () {
    $batch = uploadRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);
    Beneficiary::factory()->create(['first_name' => 'Rosa', 'middle_name' => null, 'last_name' => 'Kiat', 'rsbsa_number' => 'RSBSA-0901']);

    $this->post(route('import.confirm', $batch))->assertSessionHasErrors(['confirm' => 'Row 2: RSBSA No. RSBSA-0901 is already used by Rosa Kiat.']);
    expect($batch->fresh()->status)->toBe(ImportBatch::STAGED);
});

it('writes the import to the audit trail', function () {
    $batch = uploadRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);

    $this->post(route('import.confirm', $batch));

    $log = AuditLog::where('action', 'Imported Excel File')->sole();
    expect($log)->record_label->toBe('masterlist.xlsx')->user_id->toBe($this->encoder->id)
        ->and($log->new_values)->toMatchArray(['created' => 1, 'updated' => 0, 'records' => 0, 'skipped' => 0])
        ->and(AuditLog::where('action', 'Added Beneficiary Profile')->where('record_label', 'like', '%Pablo%')->count())->toBe(1);
});

it('keeps confirming to the uploader', function () {
    $batch = uploadRows([['Name', 'Birthdate', 'Barangay', 'RSBSA No.'], ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901']]);

    $this->actingAs($this->admin)->post(route('import.confirm', $batch))->assertNotFound();
    expect($batch->fresh()->status)->toBe(ImportBatch::STAGED);
});
