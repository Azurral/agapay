<?php

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\InventoryService;
use App\Support\DashboardStats;
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

it('shows each beneficiary\'s latest intervention and claim status', function () {
    $this->seed(InterventionSeeder::class);
    $juan = Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'rsbsa_number' => 'RSBSA-0231', 'barangay_id' => brgy('Poblacion')]);
    record($juan, program('da', 'Molasses'), ['distribution_cycle_id' => DistributionCycle::where('code', '2026-Q2')->value('id')]);
    record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-07-18']);

    $this->actingAs(userWithRole(Role::ADMIN))->get('/dashboard')
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Poblacion', '1 member', 'Certified Rice Seeds', 'Claimed'])
        ->assertDontSee('Molasses');
});

it('counts active interventions and pending validations live', function () {
    $this->seed(DatabaseSeeder::class);
    $agritech = User::where('username', 'Agritech_02')->sole();

    expect(DashboardStats::value('active_interventions', $agritech))->toBe(5)
        ->and(DashboardStats::value('pending_validation', $agritech))->toBe(2);

    $carlos = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Carlos'))->sole();
    app(ClaimService::class)->archive($carlos, 'Wrong program', User::where('username', 'Admin_01')->sole());

    expect(DashboardStats::value('active_interventions', $agritech))->toBe(4)
        ->and(DashboardStats::value('pending_validation', $agritech))->toBe(1)
        ->and($carlos->beneficiary->fresh()->latestRecord)->toBeNull();   // archived records never count as "latest"
});

it('counts low stock items live', function () {
    $this->seed(DatabaseSeeder::class);
    $encoder = User::where('username', 'Encoder_03')->sole();

    expect(DashboardStats::value('low_stock_items', $encoder))->toBe(2);

    app(InventoryService::class)->record(
        InventoryItem::where('name', 'Certified Rice Seeds')->sole(), 'in', 20, today()->toDateString(), null, $encoder,
    );
    expect(DashboardStats::value('low_stock_items', $encoder))->toBe(1);
});
