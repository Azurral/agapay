<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Role;
use Database\Seeders\BarangaySeeder;

beforeEach(function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
    $this->juan = Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'birthdate' => now()->subYears(45)->subDay()->toDateString(), 'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion'), 'rsbsa_number' => 'RSBSA-0231']);
});

function profileInput(Beneficiary $b, array $overrides = []): array
{
    return [
        'first_name' => $b->first_name, 'middle_name' => $b->middle_name, 'last_name' => $b->last_name,
        'birthdate' => $b->birthdate->toDateString(), 'house_no' => $b->house_no, 'street' => $b->street, 'sitio' => $b->sitio,
        'barangay_id' => $b->barangay_id, 'rsbsa_number' => $b->rsbsa_number, 'contact_number' => $b->contact_number,
        'farm_area_ha' => $b->farm_area_ha, 'crop_type' => $b->crop_type,
        // A one-line "address" override is the sitio/purok.
        ...(isset($overrides['address']) ? ['sitio' => $overrides['address']] : []),
        ...collect($overrides)->except('address')->all(),
    ];
}

it('shows the household banner and profile information', function () {
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion')]);

    $this->actingAs(userWithRole(Role::ADMIN))->get(route('beneficiaries.show', $this->juan))->assertOk()
        ->assertSee('BENEFICIARY PROFILE')
        ->assertSee('1 other registered member shares this address (Maria Dela Cruz) - no other claims made yet.')
        ->assertSeeInOrder(['Full Name', 'Juan Dela Cruz', 'Age', '45', 'Address', 'Purok 3, Barangay Poblacion', 'Barangay', 'Poblacion', 'RSBSA Number', 'RSBSA-0231', 'Household Number', $this->juan->household->household_no])
        ->assertSee('No interventions recorded yet.')
        ->assertSee('CLAIM VERIFICATION')->assertSee('Process Claim');
});

it('says when nobody else shares the address', function () {
    $this->actingAs(userWithRole(Role::ADMIN))->get(route('beneficiaries.show', $this->juan))
        ->assertSee('No other registered members share this address.');
});

it('shows each role its Figma action bar', function (string $role, string $title, string $button) {
    $this->actingAs(userWithRole($role))->get(route('beneficiaries.show', $this->juan))
        ->assertOk()->assertSee($title)->assertSee($button);
})->with([
    [Role::ADMIN, 'CLAIM VERIFICATION', 'Process Claim'],
    [Role::ENCODER, 'EDIT MODE', 'Save Changes'],
]);

it('shows agri techs a read-only profile without an eligibility step', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->get(route('beneficiaries.show', $this->juan))
        ->assertOk()->assertSee('BENEFICIARY PROFILE')
        ->assertDontSee('Verify Eligibility')->assertDontSee('Process Claim')->assertDontSee('Save Changes');
});

it('links the encoder list Edit chip to the edit variant', function () {
    $this->actingAs(userWithRole(Role::ENCODER))->get('/beneficiaries')
        ->assertSee(route('beneficiaries.show', ['beneficiary' => $this->juan, 'edit' => 1]), false);
});

it('lets encoders correct a profile and regroups the household', function () {
    $encoder = userWithRole(Role::ENCODER);
    $oldHousehold = $this->juan->household_id;

    $this->actingAs($encoder)->put(route('beneficiaries.update', $this->juan), profileInput($this->juan, ['address' => 'Purok 9']))
        ->assertRedirect(route('beneficiaries.show', $this->juan))->assertSessionHas('status', 'Profile saved.');

    $juan = $this->juan->fresh();
    expect($juan->address)->toBe('Purok 9')
        ->and($juan->household_id)->not->toBe($oldHousehold)
        ->and($juan->updated_by)->toBe($encoder->id)
        ->and(AuditLog::where('action', 'Updated Beneficiary Profile')->where('record_label', 'Juan Dela Cruz (RSBSA-0231)')->exists())->toBeTrue();
});

it('records an RSBSA number entered on edit', function () {
    $b = Beneficiary::factory()->create(['rsbsa_number' => null]);

    $this->actingAs(userWithRole(Role::ENCODER))->put(route('beneficiaries.update', $b), profileInput($b, ['rsbsa_number' => ' RSBSA-0777 ']))
        ->assertSessionHasNoErrors();

    expect($b->fresh())->rsbsa_number->toBe('RSBSA-0777')->rsbsaDisplay()->toBe('RSBSA-0777');
});

it('refuses a taken RSBSA number and under-age birthdates on edit', function () {
    $b = Beneficiary::factory()->create(['rsbsa_number' => 'RSBSA-0999']);
    $encoder = userWithRole(Role::ENCODER);

    $this->actingAs($encoder)->put(route('beneficiaries.update', $b), profileInput($b, ['rsbsa_number' => ' rsbsa-0231 ']))
        ->assertSessionHasErrors('rsbsa_number');
    $this->actingAs($encoder)->put(route('beneficiaries.update', $b), profileInput($b, ['birthdate' => now()->subYears(10)->toDateString()]))
        ->assertSessionHasErrors('birthdate');
    // Keeping its own number is fine.
    $this->actingAs($encoder)->put(route('beneficiaries.update', $b), profileInput($b))->assertSessionHasNoErrors();

    expect($b->fresh()->rsbsa_number)->toBe('RSBSA-0999');
});

it('only lets beneficiaries.manage holders save', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->put(route('beneficiaries.update', $this->juan), profileInput($this->juan))->assertForbidden();
});

it('returns 404 for archived beneficiaries', function () {
    $this->juan->delete();

    $this->actingAs(userWithRole(Role::ADMIN))->get('/beneficiaries/'.$this->juan->id)->assertNotFound();
});

it('audits an RSBSA number entered on edit', function () {
    $b = Beneficiary::factory()->create(['rsbsa_number' => null]);

    $this->actingAs(userWithRole(Role::ENCODER))->put(route('beneficiaries.update', $b), profileInput($b, ['rsbsa_number' => 'RSBSA-0888']));

    expect(AuditLog::where('action', 'Recorded RSBSA Number')->where('auditable_id', $b->id)->exists())->toBeTrue();
});
