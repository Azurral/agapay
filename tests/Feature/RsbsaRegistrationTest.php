<?php

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BarangaySeeder;

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

it('shows the Figma registration form to encoders and admins', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/rsbsa/register')->assertOk()
        ->assertSee('RSBSA REGISTRATION FORM')
        ->assertSeeInOrder([
            'RSBSA Registration Form (To be filled up by the beneficiary)',
            'Registered applicant → OMAG validation → endorse to DA-RFO → masterlist returns.',
            'First Name', 'Address', 'Middle Name', 'Barangay', 'Last Name', 'Contact Number',
            'Birthdate', 'Farm Location', 'Age', 'Crop Type', 'Submit Registration',
        ]);
})->with([Role::ADMIN, Role::ENCODER]);

it('lets processors view the page without the form', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/rsbsa/register')
        ->assertOk()->assertDontSee('Submit Registration');
});

it('hides the page from users with neither RSBSA permission', function () {
    $user = userWithRole(Role::ENCODER);
    $user->role->permissions()->detach(Permission::whereIn('slug', ['rsbsa.register', 'rsbsa.process'])->pluck('id'));

    $this->actingAs($user->fresh())->get('/rsbsa/register')->assertForbidden();
});

it('registers an applicant as pending validation', function () {
    $encoder = userWithRole(Role::ENCODER);

    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration())
        ->assertRedirect(route('rsbsa.register'))
        ->assertSessionHas('status', 'Juan Abenoja Dela Cruz was registered and is awaiting OMAG validation.');

    $beneficiary = Beneficiary::sole();
    expect($beneficiary->rsbsa_status)->toBe(Beneficiary::RSBSA_PENDING)
        ->and($beneficiary->created_by)->toBe($encoder->id)
        ->and($beneficiary->source)->toBe(Beneficiary::SOURCE_MANUAL)
        ->and(AuditLog::where('action', 'Added Beneficiary Profile')->where('record_label', 'Juan Abenoja Dela Cruz (pending)')->exists())->toBeTrue();
});

it('refuses applicants under 18 and future birthdates', function (string $birthdate) {
    $this->actingAs(userWithRole(Role::ENCODER))->post('/rsbsa/register', validRegistration(['birthdate' => $birthdate]))
        ->assertSessionHasErrors(['birthdate' => 'Applicant must be at least 18 years old.']);

    expect(Beneficiary::count())->toBe(0);
})->with([
    'age 17' => fn () => now()->subYears(17)->toDateString(),
    'future' => fn () => now()->addDay()->toDateString(),
]);

it('refuses a duplicate applicant ignoring case and spaces', function () {
    $encoder = userWithRole(Role::ENCODER);
    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration());

    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration(['first_name' => ' JUAN ', 'last_name' => 'dela cruz']))
        ->assertSessionHasErrors(['first_name' => 'This person is already registered.']);

    expect(Beneficiary::count())->toBe(1);
});

it('validates the contact number format', function () {
    $encoder = userWithRole(Role::ENCODER);

    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration(['contact_number' => '12345']))
        ->assertSessionHasErrors(['contact_number' => 'Use the format 09XX-XXX-XXXX.']);

    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration(['contact_number' => '09171234567']))
        ->assertSessionHasNoErrors();
});

it('only lets rsbsa.register holders submit', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->post('/rsbsa/register', validRegistration())->assertForbidden();
});
