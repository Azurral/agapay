<?php

use App\Http\Controllers\BeneficiaryRegistrationController;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BarangaySeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
});

function validRegistration(array $overrides = []): array
{
    return [
        'first_name' => 'Juan',
        'middle_name' => 'Abenoja',
        'last_name' => 'Dela Cruz',
        'birthdate' => now()->subYears(45)->toDateString(),
        'address' => 'Purok 3',
        'barangay_id' => Barangay::firstWhere('name', 'Poblacion')->id,
        'contact_number' => '0917-123-4567',
        'farm_location' => 'Poblacion (1.5 hectares)',
        'crop_type' => 'Cabbage',
        ...$overrides,
    ];
}

it('shows the add beneficiary form to encoders and admins, without the RSBSA workflow', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/beneficiaries/create')->assertOk()
        ->assertSee('ADD BENEFICIARY')
        ->assertSeeInOrder(['First Name', 'Middle Name', 'Last Name', 'Birthdate', 'RSBSA No.', 'Add Beneficiary'])
        ->assertDontSee('OMAG validation')
        ->assertDontSee('Pending RSBSA');
})->with([Role::ADMIN, Role::ENCODER]);

it('hides the form from users who cannot register beneficiaries', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/beneficiaries/create')->assertForbidden();

    $encoder = userWithRole(Role::ENCODER);
    $encoder->role->permissions()->detach(Permission::where('slug', 'rsbsa.register')->value('id'));
    $this->actingAs($encoder->fresh())->get('/beneficiaries/create')->assertForbidden();
});

it('adds a beneficiary straight away, with N/A for a missing RSBSA number', function () {
    $encoder = userWithRole(Role::ENCODER);

    $response = $this->actingAs($encoder)->post('/beneficiaries', validRegistration());

    $beneficiary = Beneficiary::sole();
    $response->assertRedirect(route('beneficiaries.show', $beneficiary))
        ->assertSessionHas('status', 'Juan Abenoja Dela Cruz was added.');
    expect($beneficiary->rsbsa_status)->toBe(Beneficiary::RSBSA_REGISTERED)
        ->and($beneficiary->rsbsaDisplay())->toBe('N/A')
        ->and($beneficiary->created_by)->toBe($encoder->id)
        ->and($beneficiary->source)->toBe(Beneficiary::SOURCE_MANUAL)
        ->and(AuditLog::where('action', 'Added Beneficiary Profile')->where('record_label', 'Juan Abenoja Dela Cruz (N/A)')->exists())->toBeTrue();
});

it('records an RSBSA number given on the form', function () {
    $this->actingAs(userWithRole(Role::ENCODER))->post('/beneficiaries', validRegistration(['rsbsa_number' => ' rsbsa-0777 ']));

    expect(Beneficiary::sole()->rsbsa_number)->toBe('RSBSA-0777');
});

it('refuses an RSBSA number another profile already has', function () {
    Beneficiary::factory()->create(['rsbsa_number' => 'RSBSA-0777']);

    $this->actingAs(userWithRole(Role::ENCODER))->post('/beneficiaries', validRegistration(['rsbsa_number' => 'rsbsa-0777']))
        ->assertSessionHasErrors(['rsbsa_number' => 'RSBSA number rsbsa-0777 is already assigned to another beneficiary.']);

    expect(Beneficiary::count())->toBe(1);
});

it('refuses applicants under 18 and future birthdates', function (string $birthdate) {
    $this->actingAs(userWithRole(Role::ENCODER))->post('/beneficiaries', validRegistration(['birthdate' => $birthdate]))
        ->assertSessionHasErrors(['birthdate' => 'Applicant must be at least 18 years old.']);

    expect(Beneficiary::count())->toBe(0);
})->with([
    'age 17' => fn () => now()->subYears(17)->toDateString(),
    'future' => fn () => now()->addDay()->toDateString(),
]);

it('refuses a duplicate applicant ignoring case and spaces', function () {
    $encoder = userWithRole(Role::ENCODER);
    $this->actingAs($encoder)->post('/beneficiaries', validRegistration());

    $this->actingAs($encoder)->post('/beneficiaries', validRegistration(['first_name' => ' JUAN ', 'last_name' => 'dela cruz']))
        ->assertSessionHasErrors(['first_name' => 'This person is already registered.']);

    expect(Beneficiary::count())->toBe(1);
});

it('validates the contact number format', function () {
    $encoder = userWithRole(Role::ENCODER);

    $this->actingAs($encoder)->post('/beneficiaries', validRegistration(['contact_number' => '12345']))
        ->assertSessionHasErrors(['contact_number' => 'Use the format 09XX-XXX-XXXX.']);

    $this->actingAs($encoder)->post('/beneficiaries', validRegistration(['contact_number' => '09171234567']))
        ->assertSessionHasNoErrors();
});

it('only lets rsbsa.register holders submit', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->post('/beneficiaries', validRegistration())->assertForbidden();
});

it('disables the submit button while the form is being sent', function () {
    $this->actingAs(userWithRole(Role::ENCODER))->get('/beneficiaries/create')
        ->assertSee('x-on:submit="busy = true"', false)
        ->assertSee(':disabled="busy"', false);
});

it('refuses a registration while the same person is already being saved', function () {
    $data = validRegistration();
    $key = BeneficiaryRegistrationController::lockKey($data['first_name'], $data['last_name'], $data['birthdate'], (int) $data['barangay_id']);
    $lock = Cache::lock($key, 10);
    $lock->get();

    $this->actingAs(userWithRole(Role::ENCODER))->post('/beneficiaries', $data)
        ->assertSessionHasErrors(['first_name' => 'This person is already registered.']);
    expect(Beneficiary::count())->toBe(0);

    $lock->release();
});

it('has no RSBSA workflow routes left', function () {
    expect(Route::has('rsbsa.register'))->toBeFalse()
        ->and(Route::has('rsbsa.transition'))->toBeFalse()
        ->and(Permission::where('slug', 'rsbsa.process')->exists())->toBeFalse();
});
