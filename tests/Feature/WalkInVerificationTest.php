<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\ImportBatch;
use App\Models\Role;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\InterventionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// No ghost farmers (panel): a walk-in registration needs a valid ID and waits for the Administrator's approval before
// the farmer can be put on any program. Farmers from the DA masterlist (Excel import) are already verified.

beforeEach(function () {
    seedRoles();
    $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
    Storage::fake('local');
    $this->admin = userWithRole(Role::ADMIN);
    $this->encoder = userWithRole(Role::ENCODER);
});

function addWalkIn(array $overrides = []): Beneficiary
{
    test()->post('/beneficiaries', validRegistration($overrides))->assertSessionHasNoErrors();

    return Beneficiary::latest('id')->firstOrFail();
}

it('requires the valid ID type, number and photo', function () {
    $this->actingAs($this->encoder)
        ->post('/beneficiaries', validRegistration(['valid_id_type' => '', 'valid_id_number' => '', 'valid_id_photo' => null]))
        ->assertSessionHasErrors([
            'valid_id_type' => 'Choose the type of valid ID.',
            'valid_id_number' => 'Enter the ID number.',
            'valid_id_photo' => 'Attach a photo or scan of the valid ID (JPG or PNG).',
        ]);
    expect(Beneficiary::count())->toBe(0);
});

it('keeps an encoder\'s walk-in as Pending approval, with the ID number encrypted and the photo private', function () {
    $this->actingAs($this->encoder);
    $farmer = addWalkIn(['valid_id_type' => 'philsys', 'valid_id_number' => '1234-5678-9012-3456']);

    expect($farmer->verification_status)->toBe('pending')
        ->and($farmer->valid_id_number)->toBe('1234-5678-9012-3456')
        ->and(DB::table('beneficiaries')->where('id', $farmer->id)->value('valid_id_number'))->not->toContain('1234')
        ->and(Storage::disk('local')->exists($farmer->valid_id_photo))->toBeTrue();

    $this->get(route('beneficiaries.show', $farmer))->assertSee('Pending approval')->assertSee('PhilSys National ID');
});

it('verifies the Administrator\'s own registrations at once', function () {
    $this->actingAs($this->admin);

    expect(addWalkIn()->verification_status)->toBe('verified');
});

it('puts no program on a farmer who is waiting for approval', function () {
    $this->actingAs($this->encoder);
    $farmer = addWalkIn();

    $this->post('/intervention-records', [
        'beneficiary_id' => $farmer->id, 'source' => 'lgu', 'intervention_id' => program('lgu', 'Municipal Cash Subsidy')->id,
        'distribution_cycle_id' => DistributionCycle::current()->id, 'distribution_status' => 'not_distributed',
    ])->assertSessionHasErrors(['beneficiary_id' => 'Juan Abenoja Dela Cruz is waiting for the Administrator\'s approval (walk-in registration).']);

    expect($farmer->interventionRecords()->count())->toBe(0);
});

it('lists walk-ins for the Administrator only', function () {
    $this->actingAs($this->encoder);
    addWalkIn();

    $this->get('/beneficiaries/approvals')->assertForbidden();
    $this->actingAs($this->admin)->get('/beneficiaries/approvals')->assertOk()
        ->assertSee('For Approval')->assertSeeInOrder(['Juan Abenoja Dela Cruz', 'Voter\'s ID', 'Approve', 'Reject']);
});

it('approves a walk-in, after which programs can be given', function () {
    $this->actingAs($this->encoder);
    $farmer = addWalkIn();

    $this->actingAs($this->admin)->post(route('beneficiaries.approve', $farmer))
        ->assertRedirect()->assertSessionHas('status', $farmer->fullName().' was approved.');

    expect($farmer->fresh())->verification_status->toBe('verified')->verified_by->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'Approved Walk-in Registration')->exists())->toBeTrue();

    $this->actingAs($this->encoder)->post('/intervention-records', [
        'beneficiary_id' => $farmer->id, 'source' => 'lgu', 'intervention_id' => program('lgu', 'Municipal Cash Subsidy')->id,
        'distribution_cycle_id' => DistributionCycle::current()->id, 'distribution_status' => 'not_distributed',
    ])->assertSessionHasNoErrors();
});

it('rejects a walk-in only with a reason', function () {
    $this->actingAs($this->encoder);
    $farmer = addWalkIn();
    $this->actingAs($this->admin);

    $this->post(route('beneficiaries.reject', $farmer), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('beneficiaries.reject', $farmer), ['reason' => 'ID does not match the name'])->assertSessionHasNoErrors();

    expect($farmer->fresh())->verification_status->toBe('rejected')->rejection_reason->toBe('ID does not match the name')
        ->and(AuditLog::where('action', 'Rejected Walk-in Registration')->exists())->toBeTrue();
});

it('shows the ID photo to signed-in staff only', function () {
    $this->actingAs($this->encoder);
    $farmer = addWalkIn();

    $this->actingAs($this->admin)->get(route('beneficiaries.valid-id', $farmer))->assertOk();
    auth()->logout();
    $this->get(route('beneficiaries.valid-id', $farmer))->assertRedirect(route('login'));
});

it('counts farmers from the Excel masterlist as verified', function () {
    $this->actingAs($this->encoder);
    $this->post('/import', ['file' => spreadsheet([['Name', 'Birthdate', 'Barangay'], ['Ramil Kinaw', '1980-05-10', 'Samoki']])]);
    $this->post(route('import.confirm', ImportBatch::latest('id')->firstOrFail()), ['consent' => '1']);

    expect(Beneficiary::where('last_name', 'Kinaw')->value('verification_status'))->toBe('verified');
});
