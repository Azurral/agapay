<?php

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->recordOf = fn (string $first) => InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', $first))->sole();
    $this->balance = fn (string $item) => InventoryItem::where('name', $item)->sole()->balance();
});

it('deducts stock when a claim is processed', function () {
    $carlos = ($this->recordOf)('Carlos');
    $this->actingAs($this->agritech)->post(route('intervention-records.validate', $carlos), ['validation_status' => 'eligible']);
    $this->actingAs($this->agritech)->post(route('intervention-records.claim', $carlos))->assertSessionHasNoErrors();

    expect(($this->balance)('Complete Fertilizer'))->toBe(29.0)
        ->and(InventoryMovement::latest('id')->first()->line())
        ->toBe('−1 sack · Complete Fertilizer · Auto-deducted: distribution to Carlos Ibanez (RSBSA-0099) · '.today()->format('M j, Y'));
});

it('blocks a claim when stock is short and leaves the record unclaimed', function () {
    $carlos = ($this->recordOf)('Carlos');
    $carlos->forceFill(['validation_status' => 'eligible', 'quantity' => 31])->saveQuietly();

    $this->actingAs($this->admin)->post(route('intervention-records.claim', $carlos))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'Not enough stock: Complete Fertilizer has 30 sacks left, 31 needed.']);

    expect($carlos->fresh()->claim_status)->toBe('unclaimed')
        ->and(($this->balance)('Complete Fertilizer'))->toBe(30.0);
});

it('returns stock on unclaim', function () {
    $this->actingAs($this->admin)->post(route('intervention-records.unclaim', ($this->recordOf)('Juan')))->assertSessionHasNoErrors();

    expect(($this->balance)('Certified Rice Seeds'))->toBe(36.0);
});

it('returns stock on archive and re-deducts on restore', function () {
    $juan = ($this->recordOf)('Juan');
    $this->actingAs($this->admin)->post(route('intervention-records.archive', $juan), ['reason' => 'Encoded twice']);
    expect(($this->balance)('Certified Rice Seeds'))->toBe(36.0);

    $this->actingAs($this->admin)->post(route('intervention-records.restore', $juan))->assertSessionHasNoErrors();
    expect(($this->balance)('Certified Rice Seeds'))->toBe(34.0);

    // Archived again, then the stock is used up: restoring would need stock that is gone.
    $this->actingAs($this->admin)->post(route('intervention-records.archive', $juan), ['reason' => 'Encoded twice']);
    app(InventoryService::class)->record(InventoryItem::where('name', 'Certified Rice Seeds')->sole(), 'out', 36, today()->toDateString(), 'Typhoon relief', $this->admin);

    $this->actingAs($this->admin)->post(route('intervention-records.restore', $juan))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'Not enough stock: Certified Rice Seeds has 0 sacks left, 2 needed.']);
    expect(InterventionRecord::onlyTrashed()->find($juan->id))->not->toBeNull()
        ->and(($this->balance)('Certified Rice Seeds'))->toBe(0.0);
});

it('adjusts stock when a claimed quantity is edited', function () {
    $juan = ($this->recordOf)('Juan');
    $base = ['source' => 'da', 'intervention_id' => $juan->intervention_id, 'distribution_cycle_id' => $juan->distribution_cycle_id,
        'distribution_status' => 'distributed', 'date_distributed' => '2026-07-18'];
    $this->actingAs($this->encoder);

    $this->put(route('intervention-records.update', $juan), [...$base, 'quantity' => 4])->assertSessionHasNoErrors();
    expect(($this->balance)('Certified Rice Seeds'))->toBe(32.0);

    $this->put(route('intervention-records.update', $juan), [...$base, 'quantity' => 40])
        ->assertSessionHasErrors(['quantity' => 'Not enough stock: Certified Rice Seeds has 32 sacks left, 36 needed.']);
    expect((float) $juan->fresh()->quantity)->toBe(4.0)
        ->and(($this->balance)('Certified Rice Seeds'))->toBe(32.0);

    $this->put(route('intervention-records.update', $juan), [...$base, 'quantity' => 1])->assertSessionHasNoErrors();
    expect(($this->balance)('Certified Rice Seeds'))->toBe(35.0);
});

it('rolls back a distributed DE record when stock is short', function () {
    $maria = Beneficiary::where(['first_name' => 'Maria', 'last_name' => 'Santos'])->sole();

    $this->actingAs($this->encoder)->post('/intervention-records', [
        'beneficiary_id' => $maria->id, 'source' => 'da', 'intervention_id' => program('da', 'Complete Fertilizer')->id,
        'quantity' => 31, 'distribution_cycle_id' => DistributionCycle::where('code', '2026-Q3')->value('id'),
        'distribution_status' => 'distributed', 'date_distributed' => '2026-07-20',
    ])->assertSessionHasErrors(['quantity' => 'Not enough stock: Complete Fertilizer has 30 sacks left, 31 needed.']);

    expect($maria->interventionRecords()->where('intervention_id', program('da', 'Complete Fertilizer')->id)->exists())->toBeFalse();
});

it('does not touch stock for cash aid', function () {
    $liza = ($this->recordOf)('Liza');   // PAFF, bedridden (claimable)
    $before = InventoryMovement::count();

    $this->actingAs($this->admin)->post(route('intervention-records.claim', $liza))->assertSessionHasNoErrors();

    expect(InventoryMovement::count())->toBe($before);
});

it('requires a quantity to claim a stocked program', function () {
    $carlos = ($this->recordOf)('Carlos');
    $carlos->forceFill(['validation_status' => 'eligible', 'quantity' => null])->saveQuietly();

    $this->actingAs($this->admin)->post(route('intervention-records.claim', $carlos))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'Enter the quantity given out: Complete Fertilizer is deducted from stock.']);
    expect($carlos->fresh()->claim_status)->toBe('unclaimed');

    $this->actingAs($this->admin)->post(route('intervention-records.claim', $carlos), ['quantity' => 2])->assertSessionHasNoErrors();
    expect(($this->balance)('Complete Fertilizer'))->toBe(28.0);
});

it('offers a quantity field in the Process Claim modal', function () {
    $liza = Beneficiary::where('first_name', 'Liza')->sole();

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $liza))->assertSee('name="quantity"', false);
});
