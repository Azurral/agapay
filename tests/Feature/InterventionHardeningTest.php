<?php

use App\Exceptions\InterventionRuleViolation;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Services\ClaimService;
use App\Support\DashboardStats;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->cycle = DistributionCycle::current();
});

it('turns a double-submitted record into a message', function () {
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
    // The second of two simultaneous saves loses the lock race.
    InterventionRecord::creating(fn () => throw new QueryException('mysql', 'insert', [], new PDOException('Deadlock found when trying to get lock', 40001)));

    $this->actingAs($this->encoder)->from('/intervention-records/create')->post('/intervention-records', [
        'beneficiary_id' => $ana->id, 'source' => 'da', 'intervention_id' => program('da', 'Certified Rice Seeds')->id,
        'distribution_cycle_id' => $this->cycle->id, 'quantity' => 1, 'distribution_status' => 'not_distributed',
    ])->assertRedirect('/intervention-records/create')
        ->assertSessionHasErrors(['intervention_id' => 'Another save for this record happened at the same moment. Check the list, then try again if it is missing.']);
});

it('leaves archived farmers out of the counters', function () {
    $pending = DashboardStats::value('pending_validation', $this->agritech);
    $record = InterventionRecord::where('validation_status', 'pending')->firstOrFail();
    $count = InterventionRecord::where(['validation_status' => 'pending', 'beneficiary_id' => $record->beneficiary_id])->count();

    $record->beneficiary->delete();

    expect(DashboardStats::value('pending_validation', $this->agritech))->toBe($pending - $count);
});

it('keeps proxy rules on claimed records', function () {
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
    $record = record($ana, program('lgu', 'Municipal Cash Subsidy'), ['validation_status' => 'eligible', 'distribution_cycle_id' => $this->cycle->id]);
    app(ClaimService::class)->claim($record, $this->admin);

    expect(fn () => app(ClaimService::class)->validate($record->fresh(), 'deceased', $this->agritech))
        ->toThrow(InterventionRuleViolation::class, 'Unclaim it first, or record the proxy.');
});

it('keeps the claim form input after a refusal', function () {
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
    $record = record($ana, program('lgu', 'Municipal Cash Subsidy'), ['validation_status' => 'deceased', 'distribution_cycle_id' => $this->cycle->id]);

    $this->actingAs($this->admin)->from(route('beneficiaries.show', $ana))
        ->post(route('intervention-records.claim', $record), ['proxy_claimant' => 'Pedro Dela Cruz', 'proof_note' => ''])
        ->assertSessionHasErrorsIn('intervention')
        ->assertSessionHasInput('proxy_claimant', 'Pedro Dela Cruz');
});

it('warns about household claims in the chosen cycle', function () {
    [$juan, $maria] = array_map(fn (string $first) => Beneficiary::factory()->create([
        'first_name' => $first, 'middle_name' => null, 'last_name' => 'Dela Cruz', 'address' => 'Purok 11 Riverside', 'barangay_id' => brgy('Samoki'),
    ]), ['Juanito', 'Marita']);
    $q2 = DistributionCycle::where('code', '2026-Q2')->sole();
    record($juan, program('da', 'Certified Rice Seeds'), ['distribution_cycle_id' => $q2->id, 'validation_status' => 'eligible']);
    $claimed = record($maria, program('da', 'Certified Rice Seeds'), ['distribution_cycle_id' => $q2->id, 'validation_status' => 'eligible', 'quantity' => 1]);
    app(ClaimService::class)->claim($claimed, $this->admin, ['date_distributed' => '2026-06-15', 'quantity' => 1], historical: true);

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $juan))
        ->assertSee('Marita Dela Cruz already claimed Certified Rice Seeds in 2026-Q2.');
});

it('tells encoders who can record a proxy claim', function () {
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
    $record = record($ana, program('lgu', 'Municipal Cash Subsidy'), ['validation_status' => 'deceased', 'distribution_cycle_id' => $this->cycle->id]);

    expect(fn () => app(ClaimService::class)->claim($record, $this->encoder, ['date_distributed' => today()->toDateString()], historical: true))
        ->toThrow(InterventionRuleViolation::class, 'Only the Administrator can record a proxy claim for a deceased beneficiary.');
});

it('shows an inactive program on edit', function () {
    $record = InterventionRecord::firstOrFail();
    $record->intervention->update(['is_active' => false]);

    $this->actingAs($this->encoder)->get(route('intervention-records.edit', $record))->assertOk()
        ->assertSee($record->intervention->name.' (inactive)');
});

it('refuses to restore a record of an archived farmer', function () {
    $record = InterventionRecord::firstOrFail();
    app(ClaimService::class)->archive($record, 'Duplicate entry', $this->admin);
    $name = $record->beneficiary->fullName();
    $record->beneficiary->delete();

    expect(fn () => app(ClaimService::class)->restore($record, $this->admin))
        ->toThrow(InterventionRuleViolation::class, "Restore {$name}'s profile first.");
});

it('restores once', function () {
    $record = InterventionRecord::where('claim_status', 'unclaimed')->firstOrFail();
    app(ClaimService::class)->archive($record, 'Duplicate entry', $this->admin);

    app(ClaimService::class)->restore($record, $this->admin);
    expect(fn () => app(ClaimService::class)->restore($record, $this->admin))
        ->toThrow(InterventionRuleViolation::class, 'This record is not archived.')
        ->and(AuditLog::where('action', 'Restored Intervention Record')->count())->toBe(1);
});
