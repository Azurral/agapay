<?php

use App\Models\Beneficiary;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BarangaySeeder;

beforeEach(function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
});

function juanAndMaria(): void
{
    Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'rsbsa_number' => null, 'barangay_id' => brgy('Poblacion')]);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos', 'rsbsa_number' => 'RSBSA-0198', 'barangay_id' => brgy('Samoki')]);
}

it('finds beneficiaries by name, full name, RSBSA number and barangay', function (string $q) {
    juanAndMaria();

    $this->actingAs(userWithRole(Role::AGRITECH))->get('/search?q='.urlencode($q))
        ->assertOk()->assertSee('SEARCH RESULTS')->assertSee('Juan Dela Cruz')->assertDontSee('Maria Santos');
})->with(['juan', 'Juan Dela', 'poblacion', '  JUAN  ']);

it('filters by barangay and by whether there is an RSBSA number', function () {
    juanAndMaria();
    $user = userWithRole(Role::ADMIN);

    $this->actingAs($user)->get('/search?barangay='.brgy('Samoki').'&rsbsa=with')
        ->assertOk()->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');

    $this->actingAs($user)->get('/search?rsbsa=none')
        ->assertOk()->assertSee('Juan Dela Cruz')->assertSee('N/A')->assertDontSee('Maria Santos');

    $this->actingAs($user)->get('/search?q=santos&rsbsa=none')
        ->assertOk()->assertSee('No beneficiaries match your search.');
});

it('finds a farmer by RSBSA number', function () {
    juanAndMaria();

    $this->actingAs(userWithRole(Role::ADMIN))->get('/search?q=rsbsa-0198')
        ->assertOk()->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');
});

it('treats hostile input as plain text', function (string $q) {
    Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz']);

    $this->actingAs(userWithRole(Role::ADMIN))->get('/search?'.$q)->assertOk()->assertDontSee('Juan Dela Cruz');
})->with(['q=%25', 'q=_', 'q=%27%22', 'q[]=x&q[]=y', 'q='.str_repeat('a', 200), 'barangay=abc&rsbsa=bogus', 'barangay[]=1']);

it('hides archived beneficiaries and blocks users without view access', function () {
    juanAndMaria();
    Beneficiary::where('first_name', 'Juan')->first()->delete();

    $this->actingAs(userWithRole(Role::ADMIN))->get('/search?q=juan')->assertSee('No beneficiaries match your search.');

    $encoder = userWithRole(Role::ENCODER);
    $encoder->role->permissions()->detach(Permission::where('slug', 'beneficiaries.view')->value('id'));
    $this->actingAs($encoder->fresh())->get('/search?q=juan')->assertForbidden();
});
