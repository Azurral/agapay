<?php

use App\Exceptions\InterventionRuleViolation;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\Role;
use App\Services\ClaimService;
use App\Services\InterventionAssignment;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\InterventionSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedRoles();
    $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
    $this->admin = userWithRole(Role::ADMIN);
    $this->agritech = userWithRole(Role::AGRITECH);
    $this->claims = app(ClaimService::class);
    $this->assign = app(InterventionAssignment::class);
    $this->q3 = DistributionCycle::where('code', '2026-Q3')->sole();
    $this->q2 = DistributionCycle::where('code', '2026-Q2')->sole();
});

it('blocks a second claim in the same household and cycle for one-per-household programs', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $paff = program('da', 'PAFF');
    $a = record($juan, $paff, ['validation_status' => 'eligible']);
    $b = record($maria, $paff, ['validation_status' => 'eligible']);
    $this->claims->claim($a, $this->admin);

    expect(fn () => $this->claims->claim($b, $this->agritech))
        ->toThrow(InterventionRuleViolation::class, 'Juan Dela Cruz already claimed PAFF for this household in 2026-Q3.');
    expect(fn () => $this->claims->claim($b, $this->agritech, ['override_reason' => 'Separate farm']))
        ->toThrow(InterventionRuleViolation::class);           // only an administrator may override

    $this->claims->claim($b, $this->admin, ['override_reason' => 'Separate farm, separate RSBSA']);

    expect($b->fresh())->claim_status->toBe('claimed')->override_reason->toBe('Separate farm, separate RSBSA')
        ->and(AuditLog::where('action', 'Claimed Intervention (Household Override)')->exists())->toBeTrue();
});

it('lets every household member claim programs that are not one-per-household', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $seeds = program('da', 'Certified Rice Seeds');
    $this->claims->claim(record($juan, $seeds, ['validation_status' => 'eligible']), $this->admin);
    $b = $this->claims->claim(record($maria, $seeds, ['validation_status' => 'eligible']), $this->admin);

    expect($b->claim_status)->toBe('claimed');
});

it('only blocks the same cycle', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $paff = program('da', 'PAFF');
    $this->claims->claim(record($juan, $paff, ['validation_status' => 'eligible', 'distribution_cycle_id' => $this->q2->id]), $this->admin);

    expect($this->claims->claim(record($maria, $paff, ['validation_status' => 'eligible']), $this->admin)->claim_status)->toBe('claimed');
});

it('ignores archived records for the household rule and the LGU duplicate flag', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $paff = program('da', 'PAFF');
    $claimed = $this->claims->claim(record($juan, $paff, ['validation_status' => 'eligible']), $this->admin);
    $this->claims->archive($claimed, 'Encoded twice', $this->admin);

    expect($this->claims->claim(record($maria, $paff, ['validation_status' => 'eligible']), $this->admin)->claim_status)->toBe('claimed');

    $fertilizer = program('lgu', 'Complete Fertilizer');
    $old = $this->claims->claim(record($juan, $fertilizer, ['validation_status' => 'eligible', 'distribution_cycle_id' => $this->q2->id]), $this->admin);
    $this->claims->archive($old, 'Wrong beneficiary', $this->admin);

    expect($this->assign->assign($juan, $fertilizer, $this->q3, [], $this->admin)->validation_status)->toBe('pending');
});

it('requires validation before a claim, except for historical encoding', function () {
    [$juan] = sameHousehold(['Juan']);
    $pending = record($juan, program('da', 'Certified Rice Seeds'));

    expect(fn () => $this->claims->claim($pending, $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'Validate eligibility first.');

    $claimed = $this->claims->claim($pending, $this->admin, ['date_distributed' => '2026-07-01'], historical: true);
    expect($claimed)->claim_status->toBe('claimed')->validation_status->toBe('eligible')
        ->and($claimed->date_distributed->toDateString())->toBe('2026-07-01');
});

it('refuses ineligible statuses', function (string $status, string $label) {
    [$juan] = sameHousehold(['Juan']);
    $record = record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => $status]);

    expect(fn () => $this->claims->claim($record, $this->admin))
        ->toThrow(InterventionRuleViolation::class, "Not eligible: {$label}.");
    expect($record->fresh()->claim_status)->toBe('unclaimed');
})->with([['inactive', 'Inactive'], ['relocated', 'Relocated'], ['duplicate', 'Duplicate']]);

it('requires a proxy and proof note for deceased beneficiaries', function () {
    [$juan] = sameHousehold(['Juan']);
    $record = record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'deceased']);

    expect(fn () => $this->claims->claim($record, $this->admin, ['proxy_claimant' => 'Maria Dela Cruz']))
        ->toThrow(InterventionRuleViolation::class, 'A deceased beneficiary can only be claimed by a proxy with a proof note.');

    $this->claims->claim($record, $this->admin, ['proxy_claimant' => 'Maria Dela Cruz', 'proof_note' => 'Death certificate No. 123, barangay-certified']);
    expect($record->fresh())->claim_status->toBe('claimed')->proxy_claimant->toBe('Maria Dela Cruz');
});

it('refuses to move a claimed record to a non-claimable status', function (string $status) {
    [$juan] = sameHousehold(['Juan']);
    $record = $this->claims->claim(record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible']), $this->admin);

    expect(fn () => $this->claims->validate($record, $status, $this->agritech))
        ->toThrow(InterventionRuleViolation::class, 'Unclaim it first.');
    expect($record->fresh()->validation_status)->toBe('eligible');
})->with(['pending', 'inactive', 'relocated', 'duplicate']);

it('validates records and refuses unknown statuses', function () {
    [$juan] = sameHousehold(['Juan']);
    $record = record($juan, program('da', 'Certified Rice Seeds'));

    $this->claims->validate($record, 'ofw', $this->agritech);
    expect($record->fresh())->validation_status->toBe('ofw')->validated_by->toBe($this->agritech->id)
        ->and(AuditLog::where('action', 'Validated Intervention Record')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'Updated Intervention Record')->exists())->toBeFalse();

    expect(fn () => $this->claims->validate($record, 'approved', $this->agritech))->toThrow(ValidationException::class);
});

it('flags a repeated LGU assistance as Duplicate unless repeats are allowed', function () {
    [$juan] = sameHousehold(['Juan']);
    $fertilizer = program('lgu', 'Complete Fertilizer');
    $seedlings = program('lgu', 'Emergency Seedlings');
    $daSeeds = program('da', 'Certified Rice Seeds');
    foreach ([$fertilizer, $seedlings, $daSeeds] as $program) {
        $this->claims->claim(record($juan, $program, ['validation_status' => 'eligible', 'distribution_cycle_id' => $this->q2->id]), $this->admin);
    }

    expect($this->assign->assign($juan, $fertilizer, $this->q3, [], $this->admin)->validation_status)->toBe('duplicate')
        ->and($this->assign->assign($juan, $seedlings, $this->q3, [], $this->admin)->validation_status)->toBe('pending')
        ->and($this->assign->assign($juan, $daSeeds, $this->q3, [], $this->admin)->validation_status)->toBe('pending');
});

it('refuses the same intervention twice in one cycle and archived beneficiaries', function () {
    [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
    $seeds = program('da', 'Certified Rice Seeds');
    $this->assign->assign($juan, $seeds, $this->q3, ['quantity' => 2], $this->admin);

    expect(fn () => $this->assign->assign($juan, $seeds, $this->q3, [], $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'Juan Dela Cruz already has Certified Rice Seeds in 2026-Q3.');

    $maria->delete();
    expect(fn () => $this->assign->assign($maria, $seeds, $this->q3, [], $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'This beneficiary is archived.');
});

it('records the assignment with its creator and an audit row', function () {
    [$juan] = sameHousehold(['Juan']);
    $record = $this->assign->assign($juan, program('da', 'Certified Rice Seeds'), $this->q3, ['quantity' => 2], $this->admin);

    expect($record)->quantity->toEqual('2.00')->created_by->toBe($this->admin->id)->claim_status->toBe('unclaimed')
        ->and(AuditLog::where('action', 'Added Intervention Record')->value('record_label'))->toBe('Juan Dela Cruz - Certified Rice Seeds (2026-Q3)');
});

it('edits quantity freely but blocks intervention changes on claimed records', function () {
    [$juan] = sameHousehold(['Juan']);
    $record = $this->claims->claim(record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible']), $this->admin);

    $this->assign->reassign($record, ['quantity' => 3], $this->admin);
    expect($record->fresh()->quantity)->toEqual('3.00');

    expect(fn () => $this->assign->reassign($record, ['intervention_id' => program('da', 'Molasses')->id], $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'Unclaim it first.');
});

it('archives with a reason and restores unless the slot is taken', function () {
    [$juan] = sameHousehold(['Juan']);
    $seeds = program('da', 'Certified Rice Seeds');
    $record = record($juan, $seeds);

    expect(fn () => $this->claims->archive($record, '  ', $this->admin))->toThrow(ValidationException::class);

    $this->claims->archive($record, 'Encoded twice', $this->admin);
    $archived = InterventionRecord::onlyTrashed()->sole();
    expect($archived)->delete_reason->toBe('Encoded twice')->deleted_by->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'Archived Intervention Record')->exists())->toBeTrue();

    $this->claims->restore($archived, $this->admin);
    expect($archived->fresh())->trashed()->toBeFalse()->delete_reason->toBeNull()
        ->and(AuditLog::where('action', 'Restored Intervention Record')->exists())->toBeTrue();

    $this->claims->archive($archived, 'Encoded twice', $this->admin);
    $this->assign->assign($juan, $seeds, $this->q3, [], $this->admin);
    expect(fn () => $this->claims->restore($archived->fresh(), $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'An active record already exists for this intervention and cycle.');
});

it('refuses to claim archived or already claimed records', function () {
    [$juan] = sameHousehold(['Juan']);
    $record = $this->claims->claim(record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible']), $this->admin);

    expect(fn () => $this->claims->claim($record, $this->admin))->toThrow(InterventionRuleViolation::class, 'Already claimed.');

    $this->claims->archive($record, 'Wrong entry', $this->admin);
    expect(fn () => $this->claims->claim($record, $this->admin))->toThrow(InterventionRuleViolation::class, 'This record is archived.');
});

it('unclaims and writes audit rows', function () {
    [$juan] = sameHousehold(['Juan']);
    $record = $this->claims->claim(record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible']), $this->admin);

    $this->claims->unclaim($record, $this->admin);

    expect($record->fresh())->claim_status->toBe('unclaimed')->date_distributed->toBeNull()
        ->and(AuditLog::pluck('action')->all())->toContain('Claimed Intervention', 'Unclaimed Intervention');
    expect(fn () => $this->claims->unclaim($record->fresh(), $this->admin))->toThrow(InterventionRuleViolation::class, 'Not claimed yet.');
});

it('refuses a future distribution date', function () {
    [$juan] = sameHousehold(['Juan']);
    $record = record($juan, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible']);

    expect(fn () => $this->claims->claim($record, $this->admin, ['date_distributed' => now()->addDay()->toDateString()]))
        ->toThrow(ValidationException::class);
});

it('keeps beneficiaries out of other households', function () {
    $other = Beneficiary::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Mendez', 'address' => 'Purok 9', 'barangay_id' => brgy('Samoki')]);
    [$juan] = sameHousehold(['Juan']);
    $paff = program('da', 'PAFF');
    $this->claims->claim(record($other, $paff, ['validation_status' => 'eligible']), $this->admin);

    expect($this->claims->claim(record($juan, $paff, ['validation_status' => 'eligible']), $this->admin)->claim_status)->toBe('claimed');
});
