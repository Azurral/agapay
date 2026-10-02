<?php

use App\Models\Beneficiary;
use App\Models\Role;
use App\Support\DashboardStats;
use Database\Seeders\BarangaySeeder;

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
    Beneficiary::factory()->create(['first_name' => 'Federico', 'middle_name' => null, 'last_name' => 'Wasing', 'source' => Beneficiary::SOURCE_IMPORT, 'encoding_issue' => 'Missing RSBSA Number']);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos']);

    $this->actingAs(userWithRole(Role::ENCODER))->get('/dashboard')
        ->assertOk()->assertSee('Pending Encoding Queue')
        ->assertSeeInOrder(['Federico Wasing', 'Excel Import', 'Missing RSBSA Number', now()->format('M j, Y'), 'Incomplete'])
        ->assertDontSee('Maria Santos');
});

it('counts beneficiary stats live', function () {
    $encoder = userWithRole(Role::ENCODER);
    Beneficiary::factory()->count(2)->create(['rsbsa_status' => Beneficiary::RSBSA_PENDING, 'created_by' => $encoder->id]);
    Beneficiary::factory()->create(['rsbsa_status' => Beneficiary::RSBSA_REGISTERED, 'encoding_issue' => 'Missing RSBSA Number']);
    Beneficiary::factory()->create()->delete();
    $admin = userWithRole(Role::ADMIN);

    expect(DashboardStats::value('total_beneficiaries', $admin))->toBe(3)
        ->and(DashboardStats::value('pending_rsbsa', $admin))->toBe(2)
        ->and(DashboardStats::value('encoded_this_month', $encoder))->toBe(2)
        ->and(DashboardStats::value('encoded_this_month', $admin))->toBe(0)
        ->and(DashboardStats::value('records_to_update', $encoder))->toBe(1);
});
