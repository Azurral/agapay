<?php

use App\Models\Beneficiary;
use App\Models\Role;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\InterventionSeeder;

beforeEach(function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
    $this->juan = Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'rsbsa_number' => 'RSBSA-0231', 'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion')]);
    Beneficiary::factory()->create(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'rsbsa_number' => 'RSBSA-0232', 'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion')]);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos', 'rsbsa_number' => 'RSBSA-0198', 'barangay_id' => brgy('Samoki')]);
    $this->encoder = userWithRole(Role::ENCODER);
});

it('lists beneficiary profiles with filters and edit links for the encoder', function () {
    $this->actingAs($this->encoder)->get('/beneficiaries')
        ->assertOk()
        ->assertSee('BENEFICIARY PROFILES')
        ->assertSee(['Search Name...', 'RSBSA Number...', 'Barangay: All', 'Intervention: All'])
        ->assertSeeInOrder(['Household', 'Status', 'Action'])
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Poblacion', '2 members', 'Edit']);
});

it('filters by name, RSBSA number and barangay', function (string $query, string $sees, string $hides) {
    $this->actingAs($this->encoder)->get('/beneficiaries?'.$query)
        ->assertOk()->assertSee($sees)->assertDontSee($hides);
})->with([
    ['name=juan', 'Juan Dela Cruz', 'Maria Santos'],
    ['name=dela+cruz', 'Ana Dela Cruz', 'Maria Santos'],
    ['rsbsa=0198', 'Maria Santos', 'Juan Dela Cruz'],
    ['name=%25&rsbsa[]=x', 'No beneficiaries match these filters.', 'Juan Dela Cruz'],
]);

it('filters by barangay', function () {
    $this->actingAs($this->encoder)->get('/beneficiaries?barangay='.brgy('Samoki'))
        ->assertOk()->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');
});

it('is only for users who manage beneficiaries', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/beneficiaries')->assertForbidden();
});

it('shows the latest intervention and its status, and filters by intervention', function () {
    $this->seed(InterventionSeeder::class);
    $seeds = program('da', 'Certified Rice Seeds');
    record($this->juan, $seeds, ['validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-07-18']);

    $this->actingAs($this->encoder)->get('/beneficiaries')
        ->assertSee('DA - Certified Rice Seeds')   // filter option
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Poblacion', 'Certified Rice Seeds', '2 members', 'Claimed', 'Edit']);

    $this->actingAs($this->encoder)->get('/beneficiaries?intervention='.$seeds->id)
        ->assertSee('Juan Dela Cruz')->assertDontSee('Maria Santos');
});
