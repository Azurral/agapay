<?php

use App\Models\AssistanceRequest;
use App\Models\Beneficiary;
use App\Models\DamageReport;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Support\DashboardSummary;
use Database\Seeders\DatabaseSeeder;

// Panel (Ma'am Libby): the home page sums up the work per quarter and per year. 2025 is used so the sample records
// (all 2026) stay out of the figures.

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();

    $claimed = fn (Beneficiary $who, string $source, string $name, string $date, ?float $qty = null) => record($who, program($source, $name), [
        'claim_status' => InterventionRecord::CLAIM_CLAIMED, 'date_distributed' => $date, 'quantity' => $qty,
    ]);
    $ana = Beneficiary::factory()->create(['created_at' => '2025-02-10']);
    $ben = Beneficiary::factory()->create(['created_at' => '2025-05-03']);
    $claimed($ana, 'da', 'Certified Rice Seeds', '2025-02-20', 2);
    $claimed($ana, 'lgu', 'Municipal Cash Subsidy', '2025-03-01', 5000);
    $claimed($ben, 'lgu', 'Municipal Cash Subsidy', '2025-08-15', 3000);
    record($ben, program('lgu', 'Emergency Seedlings'), ['claim_status' => InterventionRecord::CLAIM_UNCLAIMED]);   // not released: not counted

    AssistanceRequest::factory()->create(['created_at' => '2025-08-01', 'status' => 'approved', 'decided_at' => '2025-08-05']);
    AssistanceRequest::factory()->create(['created_at' => '2025-11-20', 'status' => 'pending']);
    DamageReport::factory()->create(['created_at' => '2025-11-02', 'cost' => 12000]);
});

it('sums up each quarter and the whole year', function () {
    $rows = collect(DashboardSummary::build(2025)['rows'])->keyBy('key');

    expect($rows['registered']['values'])->toBe([1, 1, 0, 0, 2])          // Q1, Q2, Q3, Q4, year
        ->and($rows['served']['values'])->toBe([1, 0, 1, 0, 2])           // distinct farmers who claimed
        ->and($rows['released_da']['values'])->toBe([1, 0, 0, 0, 1])
        ->and($rows['released_lgu']['values'])->toBe([1, 0, 1, 0, 2])
        ->and($rows['cash']['values'])->toBe([5000.0, 0.0, 3000.0, 0.0, 8000.0])
        ->and($rows['requests']['values'])->toBe([0, 0, 1, 1, 2])
        ->and($rows['approved']['values'])->toBe([0, 0, 1, 0, 1])
        ->and($rows['crisis_reports']['values'])->toBe([0, 0, 0, 1, 1])
        ->and($rows['damage_cost']['values'])->toBe([0.0, 0.0, 0.0, 12000.0, 12000.0]);
});

it('offers the years that have records, and shows the chosen year on Home', function () {
    expect(DashboardSummary::build(2025)['years'])->toContain(2025)->toContain(2026);

    $this->actingAs($this->admin)->get('/dashboard?year=2025')->assertOk()
        ->assertSeeInOrder(['Quarterly and Yearly Summary', '2025', 'Q1', 'Q2', 'Q3', 'Q4', 'Whole year'])
        ->assertSeeInOrder(['Cash given out', '₱5,000', '₱0', '₱3,000', '₱0', '₱8,000']);
});

it('is on every role\'s home page by default and can be hidden in Customize', function () {
    foreach (['Admin_01', 'Agritech_02', 'Encoder_03'] as $username) {
        $this->actingAs(User::where('username', $username)->sole())->get('/dashboard')->assertOk()->assertSee('Quarterly and Yearly Summary');
    }

    $this->actingAs($this->admin)->put(route('dashboard.customize'), ['widgets' => ['tile_farmers']]);
    $this->get('/dashboard')->assertDontSee('Quarterly and Yearly Summary');
});
