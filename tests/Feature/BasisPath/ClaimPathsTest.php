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

// Basis path testing of ClaimService::claim (manuscript section 3.4.2). Decisions:
//   P1 input invalid?  P2 already claimed?  P3 DA program and no RSBSA No.?  P4 status not claimable?
//   P5 same LGU assistance claimed in an earlier cycle?  P6 stocked program with no quantity?  P7 deceased?
//   P8 actor not the Administrator?  P9 proxy or proof note missing?  P10 household member already claimed?
//   P11 no override reason, or actor not the Administrator?  P12 household override used?  P13 historical encoding?
// V(G) = 13 + 1 = 14. Thirteen paths are feasible; the fourteenth (P10 false, P12 true) is not, because a claim can
// only be an override after P10 found a household claim.

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

it('path 3: P2 false, P3 true - the RSBSA No. was removed after the record was made', function () {
    $farmer = Beneficiary::factory()->create(['first_name' => 'Joy', 'middle_name' => null, 'last_name' => 'Kiwang']);
    $record = record($farmer, program('da', 'Certified Rice Seeds'));
    $farmer->forceFill(['rsbsa_number' => null])->saveQuietly();

    expect(fn () => $this->claims->claim($record, $this->admin))
        ->toThrow(InterventionRuleViolation::class, "Joy Kiwang has no RSBSA No. DA programs need one; LGU programs don't.");
});

it('path 4: P3 false, P4 true - a Relocated record cannot be claimed', function () {
    $record = record(Beneficiary::factory()->create(), program('da', 'Certified Rice Seeds'), ['validation_status' => 'relocated']);

    expect(fn () => $this->claims->claim($record, $this->admin))->toThrow(InterventionRuleViolation::class, 'Not eligible: Relocated.');
});

it('path 5: P4 false, P5 true - the same LGU assistance again', function () {
    $farmer = Beneficiary::factory()->create(['rsbsa_number' => null]);
    $cash = program('lgu', 'Municipal Cash Subsidy');
    $this->claims->claim(record($farmer, $cash, ['distribution_cycle_id' => $this->q2->id]), $this->admin);

    expect(fn () => $this->claims->claim(record($farmer, $cash), $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'Not eligible: Duplicate - Municipal Cash Subsidy was already received in 2026-Q2.');
});

it('path 6: P5 false, P6 true - a stocked program needs the quantity given out', function () {
    $seeds = program('da', 'Certified Rice Seeds');
    $seeds->update(['inventory_item_id' => InventoryItem::create(['name' => 'Certified Rice Seeds', 'unit' => 'sack', 'unit_label' => 'sacks', 'low_stock_threshold' => 0])->id]);
    $record = record(Beneficiary::factory()->create(), $seeds, ['quantity' => null]);

    expect(fn () => $this->claims->claim($record, $this->admin, ['quantity' => 0]))
        ->toThrow(InterventionRuleViolation::class, 'Enter the quantity given out: Certified Rice Seeds is deducted from stock.');
});

it('path 7: P6 false, P7 true, P8 false, P9 true - the Administrator must name a proxy and proof', function () {
    $record = record(Beneficiary::factory()->create(), program('da', 'PAFF'), ['validation_status' => 'deceased']);

    expect(fn () => $this->claims->claim($record, $this->admin, ['proxy_claimant' => 'Teresa (spouse)']))
        ->toThrow(InterventionRuleViolation::class, 'A deceased beneficiary can only be claimed by a proxy with a proof note.');
});

it('path 8: P7 true, P8 true - only the Administrator can record a proxy claim, even when the request names one', function () {
    $record = record(Beneficiary::factory()->create(), program('da', 'PAFF'), ['validation_status' => 'deceased']);

    expect(fn () => $this->claims->claim($record, $this->agritech, ['proxy_claimant' => 'Spouse', 'proof_note' => 'Death cert.']))
        ->toThrow(InterventionRuleViolation::class, 'Only the Administrator can record a proxy claim for a deceased beneficiary.')
        ->and($record->fresh()->claim_status)->toBe('unclaimed');
});

it('path 9: P7 true, P8 false, P9 false - the Administrator records the proxy claim', function () {
    $deceased = record(Beneficiary::factory()->create(), program('da', 'PAFF'), ['validation_status' => 'deceased']);

    $claimed = $this->claims->claim($deceased, $this->admin, ['proxy_claimant' => 'Teresa (spouse)', 'proof_note' => 'Death certificate No. 114']);

    expect($claimed->claim_status)->toBe('claimed')->and($claimed->proxy_claimant)->toBe('Teresa (spouse)');
});

it('path 10: P7 false, P10 true, P11 true - the household already claimed', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $paff = program('da', 'PAFF');
    $this->claims->claim(record($juan, $paff), $this->admin);

    expect(fn () => $this->claims->claim(record($maria, $paff), $this->agritech, ['override_reason' => 'Separate farm']))
        ->toThrow(InterventionRuleViolation::class, 'Juan Dela Cruz already claimed PAFF for this household in 2026-Q3.');
});

it('path 11: P10 true, P11 false, P12 true - the Administrator overrides the household rule', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $paff = program('da', 'PAFF');
    $this->claims->claim(record($juan, $paff), $this->admin);

    $claimed = $this->claims->claim(record($maria, $paff), $this->admin, ['override_reason' => 'Separate farm, verified']);

    expect($claimed->claim_status)->toBe('claimed')->and($claimed->override_reason)->toBe('Separate farm, verified')
        ->and(AuditLog::latest('id')->value('action'))->toBe('Claimed Intervention (Household Override)');
});

it('path 12: P10 false, P12 false, P13 true - a past distribution from the logbook', function () {
    $claimed = $this->claims->claim(record(Beneficiary::factory()->create(), program('da', 'PAFF')), $this->admin, ['date_distributed' => '2026-07-01'], historical: true);

    expect($claimed->claim_status)->toBe('claimed')
        ->and(AuditLog::latest('id')->value('action'))->toBe('Claimed Intervention (Historical Encoding)');
});

it('path 13: every decision false - an ordinary claim', function () {
    $claimed = $this->claims->claim(record(Beneficiary::factory()->create(), program('da', 'PAFF')), $this->agritech);

    expect($claimed->claim_status)->toBe('claimed')
        ->and(AuditLog::latest('id')->value('action'))->toBe('Claimed Intervention');
});
