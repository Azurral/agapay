<?php

use App\Exceptions\InterventionRuleViolation;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Role;
use App\Services\ClaimService;
use App\Services\InterventionAssignment;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\InterventionSeeder;

// Basis path testing of InterventionAssignment::assign, with ensureRsbsaFor, ensureSlotFree and earlierClaim
// (manuscript section 3.4.2). Decisions:
//   P1 beneficiary archived?  P2 DA program and no RSBSA No.?  P3 same program already in this cycle?
//   P4 DA program, or repeats allowed?  P5 claimed in an earlier cycle?
// V(G) = 5 + 1 = 6 independent paths.

beforeEach(function () {
    seedRoles();
    $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
    $this->admin = userWithRole(Role::ADMIN);
    $this->assign = app(InterventionAssignment::class);
    $this->q2 = DistributionCycle::where('code', '2026-Q2')->sole();
    $this->q3 = DistributionCycle::where('code', '2026-Q3')->sole();
});

it('path 1: P1 true - an archived beneficiary gets nothing', function () {
    $farmer = Beneficiary::factory()->create();
    $farmer->delete();

    expect(fn () => $this->assign->assign($farmer, program('lgu', 'Emergency Seedlings'), $this->q3, [], $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'This beneficiary is archived.');
});

it('path 2: P1 false, P2 true - a DA program needs an RSBSA No.', function () {
    $farmer = Beneficiary::factory()->create(['first_name' => 'Joy', 'middle_name' => null, 'last_name' => 'Kiwang', 'rsbsa_number' => null]);

    expect(fn () => $this->assign->assign($farmer, program('da', 'Certified Rice Seeds'), $this->q3, [], $this->admin))
        ->toThrow(InterventionRuleViolation::class, "Joy Kiwang has no RSBSA No. DA programs need one; LGU programs don't.");
});

it('path 3: P2 false, P3 true - the same program twice in one cycle', function () {
    $farmer = Beneficiary::factory()->create(['first_name' => 'Ramon', 'middle_name' => null, 'last_name' => 'Chalsiw']);
    $seeds = program('da', 'Certified Rice Seeds');
    $this->assign->assign($farmer, $seeds, $this->q3, [], $this->admin);

    expect(fn () => $this->assign->assign($farmer, $seeds, $this->q3, [], $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'Ramon Chalsiw already has Certified Rice Seeds in 2026-Q3.');
});

it('path 4: P3 false, P4 true - a DA program is never a repeat', function () {
    $farmer = Beneficiary::factory()->create();
    $seeds = program('da', 'Certified Rice Seeds');
    app(ClaimService::class)->claim($this->assign->assign($farmer, $seeds, $this->q2, [], $this->admin), $this->admin);

    expect($this->assign->assign($farmer, $seeds, $this->q3, [], $this->admin)->validation_status)->toBe('eligible');
});

it('path 5: P4 false, P5 false - an LGU program never given before', function () {
    $farmer = Beneficiary::factory()->create(['rsbsa_number' => null]);

    expect($this->assign->assign($farmer, program('lgu', 'Municipal Cash Subsidy'), $this->q3, [], $this->admin)->validation_status)
        ->toBe('eligible');
});

it('path 6: P4 false, P5 true - an LGU program already claimed in an earlier cycle is a Duplicate', function () {
    $farmer = Beneficiary::factory()->create(['rsbsa_number' => null]);
    $cash = program('lgu', 'Municipal Cash Subsidy');
    app(ClaimService::class)->claim($this->assign->assign($farmer, $cash, $this->q2, [], $this->admin), $this->admin);

    expect($this->assign->assign($farmer, $cash, $this->q3, [], $this->admin)->validation_status)->toBe('duplicate');
});
