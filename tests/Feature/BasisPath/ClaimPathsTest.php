<?php

use App\Exceptions\InterventionRuleViolation;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Services\ClaimService;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\InterventionSeeder;
use Illuminate\Validation\ValidationException;

// Basis path testing of ClaimService::claim (manuscript section 3.4.2, Figure 23). Decisions, in code order:
//   P1 input invalid (e.g. a future date)?  P2 already claimed?  P3 forwarded to a later cycle or closed after its
//   claiming period?  P4 DA program and no RSBSA No.?  P5 status not claimable (only Eligible is; OFW, Bedridden,
//   Deceased, Inactive, Relocated and Duplicate are not)?  P6 same LGU assistance claimed in an earlier cycle?
//   P7 stocked program with no quantity?  P8 household member already claimed?  P9 no override reason, or actor not the
//   Administrator?  P10 household override used?  P11 historical encoding?
// V(G) = 11 + 1 = 12. Eleven paths are feasible; the twelfth (P8 false, P10 true) is not, because a claim can only be an
// override after P8 found a household claim.

beforeEach(function () {
    seedRoles();
    $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
    $this->admin = userWithRole(Role::ADMIN);
    $this->agritech = userWithRole(Role::AGRITECH);
    $this->claims = app(ClaimService::class);
    $this->q2 = DistributionCycle::where('code', '2026-Q2')->sole();
});

it('path 1: P1 true - a future distribution date is refused', function () {
    $record = record(Beneficiary::factory()->create(), program('da', 'Certified Rice Seeds'));

    expect(fn () => $this->claims->claim($record, $this->admin, ['date_distributed' => now()->addDay()->toDateString()]))
        ->toThrow(ValidationException::class, 'The distribution date cannot be in the future.');
});

it('path 2: P1 false, P2 true - a record cannot be claimed twice', function () {
    $record = record(Beneficiary::factory()->create(), program('da', 'Certified Rice Seeds'));
    $this->claims->claim($record, $this->admin);

    expect(fn () => $this->claims->claim($record, $this->admin))->toThrow(InterventionRuleViolation::class, 'Already claimed.');
});

it('path 3: P2 false, P3 true - a record forwarded to a later claiming date is released there, not here', function () {
    $record = record(Beneficiary::factory()->create(), program('da', 'Certified Rice Seeds'));
    $record->forceFill(['forward_status' => 'forwarded'])->saveQuietly();

    expect(fn () => $this->claims->claim($record, $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'This record was forwarded to a later claiming date; release it there.');
});

it('path 4: P3 false, P4 true - the RSBSA No. was removed after the record was made', function () {
    $farmer = Beneficiary::factory()->create(['first_name' => 'Joy', 'middle_name' => null, 'last_name' => 'Kiwang']);
    $record = record($farmer, program('da', 'Certified Rice Seeds'));
    $farmer->forceFill(['rsbsa_number' => null])->saveQuietly();

    expect(fn () => $this->claims->claim($record, $this->admin))
        ->toThrow(InterventionRuleViolation::class, "Joy Kiwang has no RSBSA No. DA programs need one; LGU programs don't.");
});

it('path 5: P4 false, P5 true - a Deceased record cannot be claimed, even by a proxy', function () {
    $record = record(Beneficiary::factory()->create(), program('da', 'Certified Rice Seeds'), ['validation_status' => 'deceased']);

    expect(fn () => $this->claims->claim($record, $this->admin, ['proxy_claimant' => 'Teresa (spouse)', 'proof_note' => 'Death certificate']))
        ->toThrow(InterventionRuleViolation::class, 'Not eligible: Deceased.');
});

it('path 6: P5 false, P6 true - the same LGU assistance again', function () {
    $farmer = Beneficiary::factory()->create(['rsbsa_number' => null]);
    $cash = program('lgu', 'Municipal Cash Subsidy');
    $this->claims->claim(record($farmer, $cash, ['distribution_cycle_id' => $this->q2->id]), $this->admin);

    expect(fn () => $this->claims->claim(record($farmer, $cash), $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'Not eligible: Duplicate - Municipal Cash Subsidy was already received in 2026-Q2.');
});

it('path 7: P6 false, P7 true - a stocked program needs the quantity given out', function () {
    $seeds = program('da', 'Certified Rice Seeds');
    $seeds->update(['inventory_item_id' => InventoryItem::create(['name' => 'Certified Rice Seeds', 'unit' => 'sack', 'unit_label' => 'sacks', 'low_stock_threshold' => 0])->id]);
    $record = record(Beneficiary::factory()->create(), $seeds, ['quantity' => null]);

    expect(fn () => $this->claims->claim($record, $this->admin, ['quantity' => 0]))
        ->toThrow(InterventionRuleViolation::class, 'Enter the quantity given out: Certified Rice Seeds is deducted from stock.');
});

it('path 8: P7 false, P8 true, P9 true - the household already claimed', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $paff = program('da', 'PAFF');
    $this->claims->claim(record($juan, $paff), $this->admin);

    expect(fn () => $this->claims->claim(record($maria, $paff), $this->agritech, ['override_reason' => 'Separate farm']))
        ->toThrow(InterventionRuleViolation::class, 'Juan Dela Cruz already claimed PAFF for this household in 2026-Q3.');
});

it('path 9: P8 true, P9 false, P10 true - the Administrator overrides the household rule', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $paff = program('da', 'PAFF');
    $this->claims->claim(record($juan, $paff), $this->admin);

    $claimed = $this->claims->claim(record($maria, $paff), $this->admin, ['override_reason' => 'Separate farm, verified']);

    expect($claimed->claim_status)->toBe('claimed')->and($claimed->override_reason)->toBe('Separate farm, verified')
        ->and(AuditLog::latest('id')->value('action'))->toBe('Claimed Intervention (Household Override)');
});

it('path 10: P8 false, P10 false, P11 true - a past distribution from the logbook', function () {
    $claimed = $this->claims->claim(record(Beneficiary::factory()->create(), program('da', 'PAFF')), $this->admin, ['date_distributed' => '2026-07-01'], historical: true);

    expect($claimed->claim_status)->toBe('claimed')
        ->and(AuditLog::latest('id')->value('action'))->toBe('Claimed Intervention (Historical Encoding)');
});

it('path 11: every decision false - an ordinary claim', function () {
    $claimed = $this->claims->claim(record(Beneficiary::factory()->create(), program('da', 'PAFF')), $this->agritech);

    expect($claimed->claim_status)->toBe('claimed')
        ->and(AuditLog::latest('id')->value('action'))->toBe('Claimed Intervention');
});
