<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->q3 = DistributionCycle::where('code', '2026-Q3')->value('id');
    $this->person = fn (string $first, string $last) => Beneficiary::where(['first_name' => $first, 'last_name' => $last])->sole();
});

function recordInput(array $overrides = []): array
{
    return [
        'beneficiary_id' => Beneficiary::where(['first_name' => 'Maria', 'last_name' => 'Santos'])->value('id'),
        'source' => 'da',
        'intervention_id' => program('da', 'Certified Rice Seeds')->id,
        'quantity' => 2,
        'distribution_cycle_id' => DistributionCycle::where('code', '2026-Q3')->value('id'),
        'date_distributed' => '',
        'distribution_status' => 'not_distributed',
        ...$overrides,
    ];
}

it('lists records with Figma columns for the encoder', function () {
    $this->actingAs($this->encoder)->get('/intervention-records')->assertOk()
        ->assertSee('INTERVENTION RECORDS')
        ->assertSee('+ Add Record')
        ->assertSeeInOrder(['Qty / Unit', 'Date Distributed', 'Action', 'Distribution Status'])
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'DA - Certified Rice Seeds (Batch 2026-Q3)', '2 sacks', 'Jul 18, 2026', 'Claimed', 'Edit'])
        ->assertSee('LGU - Emergency Seedlings (Batch 2026-Q3)')
        ->assertSee('name="claim_status"', false)
        ->assertDontSee('Federico Wasing');

    $this->actingAs($this->encoder)->get('/intervention-records?intervention='.program('da', 'PAFF')->id)
        ->assertSee('Liza Domingo')->assertDontSee('Juan Dela Cruz');
});

it('adds a not-yet-distributed record', function () {
    $this->actingAs($this->encoder)->post('/intervention-records', recordInput())
        ->assertRedirect(route('intervention-records.index'))->assertSessionHas('status', 'Intervention record saved.');

    $record = InterventionRecord::where('beneficiary_id', recordInput()['beneficiary_id'])->where('intervention_id', recordInput()['intervention_id'])->sole();
    expect($record)->validation_status->toBe('pending')->claim_status->toBe('unclaimed')->created_by->toBe($this->encoder->id)
        ->and(AuditLog::where('action', 'Added Intervention Record')->exists())->toBeTrue();
});

it('adds a distributed record as a historical claim', function () {
    $this->actingAs($this->encoder)->post('/intervention-records', recordInput([
        'distribution_status' => 'distributed', 'date_distributed' => '2026-07-20',
    ]))->assertSessionHasNoErrors();

    $record = InterventionRecord::where('beneficiary_id', recordInput()['beneficiary_id'])->where('intervention_id', recordInput()['intervention_id'])->sole();
    expect($record)->validation_status->toBe('eligible')->claim_status->toBe('claimed')
        ->and($record->date_distributed->toDateString())->toBe('2026-07-20')
        ->and(AuditLog::where('action', 'Claimed Intervention (Historical Encoding)')->exists())->toBeTrue();
});

it('rolls back a distributed record that breaks the household rule', function () {
    $juan = ($this->person)('Juan', 'Dela Cruz');
    $maria = ($this->person)('Maria', 'Dela Cruz');
    record($juan, program('da', 'PAFF'), ['validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-07-18']);

    $this->actingAs($this->encoder)->post('/intervention-records', recordInput([
        'beneficiary_id' => $maria->id, 'intervention_id' => program('da', 'PAFF')->id,
        'distribution_status' => 'distributed', 'date_distributed' => '2026-07-20',
    ]))->assertSessionHasErrors(['distribution_status' => 'Juan Dela Cruz already claimed PAFF for this household in 2026-Q3.']);

    expect($maria->interventionRecords()->count())->toBe(0);
});

it('refuses unknown or archived beneficiaries and mismatched programs', function (Closure $overrides, string $field) {
    $this->actingAs($this->encoder)->post('/intervention-records', recordInput($overrides()))
        ->assertSessionHasErrors($field);
})->with([
    'unknown beneficiary' => [fn () => ['beneficiary_id' => 99999], 'beneficiary_id'],
    'no beneficiary picked' => [fn () => ['beneficiary_id' => ''], 'beneficiary_id'],
    'array beneficiary' => [fn () => ['beneficiary_id' => [1]], 'beneficiary_id'],
    'archived beneficiary' => [function () {
        $b = Beneficiary::where('first_name', 'Rosa')->sole();
        $b->delete();

        return ['beneficiary_id' => $b->id];
    }, 'beneficiary_id'],
    'program mismatch' => [fn () => ['intervention_id' => program('lgu', 'Emergency Seedlings')->id], 'intervention_id'],
    'bad program' => [fn () => ['source' => 'xyz'], 'source'],
    'distributed without date' => [fn () => ['distribution_status' => 'distributed'], 'date_distributed'],
    'future date' => [fn () => ['distribution_status' => 'distributed', 'date_distributed' => now()->addDay()->toDateString()], 'date_distributed'],
    'negative quantity' => [fn () => ['quantity' => -1], 'quantity'],
]);

it('refuses the same intervention twice in a cycle', function () {
    $this->actingAs($this->encoder)->post('/intervention-records', recordInput([
        'beneficiary_id' => ($this->person)('Juan', 'Dela Cruz')->id,
    ]))->assertSessionHasErrors(['intervention_id' => 'Juan Dela Cruz already has Certified Rice Seeds in 2026-Q3.']);
});

it('edits quantity and blocks intervention changes on claimed records', function () {
    $juan = ($this->person)('Juan', 'Dela Cruz');
    $record = $juan->interventionRecords()->sole();
    $this->actingAs($this->encoder);

    $this->get(route('intervention-records.edit', $record))->assertOk()
        ->assertSee('Edit Intervention Record')->assertSee('Juan Dela Cruz')->assertSee('value="2"', false);

    $base = ['source' => 'da', 'intervention_id' => $record->intervention_id, 'distribution_cycle_id' => $this->q3,
        'quantity' => 3, 'distribution_status' => 'distributed', 'date_distributed' => '2026-07-18'];
    $this->put(route('intervention-records.update', $record), $base)->assertRedirect(route('intervention-records.index'));
    expect($record->fresh()->quantity)->toEqual('3.00');

    $this->put(route('intervention-records.update', $record), [...$base, 'intervention_id' => program('da', 'Molasses')->id])
        ->assertSessionHasErrors(['intervention_id' => 'Unclaim it first.']);

    $this->put(route('intervention-records.update', $record), [...$base, 'distribution_status' => 'not_distributed', 'date_distributed' => ''])
        ->assertSessionHasNoErrors();
    expect($record->fresh()->claim_status)->toBe('unclaimed');
});

it('does not let the encoder dropdown skip Agri Tech validation', function () {
    $pedro = ($this->person)('Pedro', 'Reyes')->interventionRecords()->sole();

    $this->actingAs($this->encoder)->get('/intervention-records')->assertDontSee('name="historical"', false);
    $this->actingAs($this->encoder)->post(route('intervention-records.claim', $pedro), ['historical' => 1])
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'Validate eligibility first.']);
    expect($pedro->fresh())->claim_status->toBe('unclaimed')->validation_status->toBe('pending');
});

it('returns beneficiary lookup results', function () {
    $this->actingAs($this->encoder)->getJson('/beneficiary-lookup?q=juan')->assertOk()
        ->assertExactJson([['id' => ($this->person)('Juan', 'Dela Cruz')->id, 'name' => 'Juan Dela Cruz', 'rsbsa' => 'RSBSA-0231', 'barangay' => 'Poblacion',
            'barangay_id' => ($this->person)('Juan', 'Dela Cruz')->barangay_id, 'farm_location' => null, 'crop_type' => 'Rice']]);

    $this->actingAs($this->encoder)->getJson('/beneficiary-lookup?q[]=juan')->assertOk()->assertExactJson([]);
    $this->actingAs($this->encoder)->getJson('/beneficiary-lookup?q=%25')->assertOk()->assertExactJson([]);
});

it('shows the add form with Figma fields', function () {
    $this->actingAs($this->encoder)->get('/intervention-records/create')->assertOk()
        ->assertSee('Add Intervention Record')
        ->assertSeeInOrder(['Beneficiary:', 'Program:', 'Intervention Type:', 'Quantity', 'Batch / Cycle:', 'Date Distributed:', 'Distribution Status:', 'Save Intervention Record'])
        ->assertSee('2026-Q3');
});

it('forbids agri techs', function () {
    $this->actingAs($this->agritech)->get('/intervention-records')->assertForbidden();
    $this->actingAs($this->agritech)->post('/intervention-records', recordInput())->assertForbidden();
});
