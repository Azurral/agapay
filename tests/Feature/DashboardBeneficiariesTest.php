<?php

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\ClaimService;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InterventionSeeder;

beforeEach(function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
});

it('shows recent beneficiaries with household size on the admin dashboard', function () {
    $poblacion = brgy('Poblacion');
    Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'rsbsa_number' => 'RSBSA-0231', 'address' => 'Purok 3', 'barangay_id' => $poblacion]);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'address' => 'purok 3.', 'barangay_id' => $poblacion]);

    $this->actingAs(userWithRole(Role::ADMIN))->get('/dashboard')
        ->assertOk()->assertSee('All Beneficiaries')
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Poblacion', '2 members']);
});

it('shows the encoder their pending encoding queue', function () {
    Beneficiary::factory()->create(['first_name' => 'Federico', 'middle_name' => null, 'last_name' => 'Wasing', 'source' => Beneficiary::SOURCE_IMPORT, 'encoding_issue' => 'Awaiting Barangay Confirmation']);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos']);

    $this->actingAs(userWithRole(Role::ENCODER))->get('/dashboard')
        ->assertOk()->assertSee('Pending Encoding Queue')
        ->assertSeeInOrder(['Federico Wasing', 'Excel Import', 'Awaiting Barangay Confirmation', now()->format('M j, Y'), 'Incomplete'])
        ->assertDontSee('Maria Santos');
});

it('shows each beneficiary\'s latest intervention and claim status', function () {
    $this->seed(InterventionSeeder::class);
    $juan = Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'rsbsa_number' => 'RSBSA-0231', 'barangay_id' => brgy('Poblacion')]);
    record($juan, program('da', 'Molasses'), ['distribution_cycle_id' => DistributionCycle::where('code', '2026-Q2')->value('id')]);
    record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-07-18']);

    $this->actingAs(userWithRole(Role::ADMIN))->get('/dashboard')
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Poblacion', '1 member', 'Certified Rice Seeds', 'Claimed'])
        ->assertDontSee('Molasses');
});

it('never counts archived records as the latest', function () {
    $this->seed(DatabaseSeeder::class);

    $carlos = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Carlos'))->sole();
    app(ClaimService::class)->archive($carlos, 'Wrong program', User::where('username', 'Admin_01')->sole());

    expect($carlos->beneficiary->fresh()->latestRecord)->toBeNull();
});
