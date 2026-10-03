<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\ClaimService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InventorySeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
});

function itemInput(array $overrides = []): array
{
    return ['name' => 'Garden Tools', 'unit' => 'set', 'unit_label' => 'sets', 'low_stock_threshold' => '5', 'interventions' => [], ...$overrides];
}

it('rejects forged item input with field errors', function (array $overrides, string $field) {
    $this->actingAs($this->admin)->from('/inventory')->post(route('inventory.items.store'), itemInput($overrides))
        ->assertRedirect('/inventory')->assertSessionHasErrorsIn('item', [$field]);
})->with([
    'array name' => [['name' => ['x']], 'name'],
    'array unit' => [['unit' => ['x']], 'unit'],
    'array threshold' => [['low_stock_threshold' => ['5']], 'low_stock_threshold'],
    'string interventions' => [['interventions' => 'abc'], 'interventions'],
    'unknown program' => [['interventions' => [99999]], 'interventions.0'],
]);

it('turns a simultaneous item name into a field error', function () {
    InventoryItem::creating(function (InventoryItem $item) {
        if (! InventoryItem::where('name', 'Garden Tools')->exists()) {
            InventoryItem::withoutEvents(fn () => InventoryItem::forceCreate(['name' => 'Garden Tools', 'unit' => 'set', 'unit_label' => 'sets', 'low_stock_threshold' => 0]));
        }
    });

    $this->actingAs($this->admin)->post(route('inventory.items.store'), itemInput())
        ->assertSessionHasErrorsIn('item', ['name' => 'An item with this name already exists.']);
});

it('fits long names in an automatic movement note', function () {
    $farmer = Beneficiary::factory()->create(['first_name' => str_repeat('A', 100), 'middle_name' => str_repeat('B', 100), 'last_name' => str_repeat('C', 100)]);
    $record = record($farmer, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible', 'quantity' => 1]);

    app(ClaimService::class)->claim($record, $this->admin, ['quantity' => 1]);

    $note = InventoryMovement::where('intervention_record_id', $record->id)->sole()->notes;
    expect(mb_strlen($note))->toBeLessThanOrEqual(255)->and($note)->toEndWith('…');
});

it('re-seeds inventory without duplicating or negative movements', function () {
    // A dev database where more was claimed through AGAPAY than the seeded logbook stock-out (86 sacks).
    $rice = InventoryItem::where('name', 'Certified Rice Seeds')->sole();
    InventoryMovement::create(['inventory_item_id' => $rice->id, 'direction' => 'in', 'quantity' => 300, 'movement_date' => today(), 'notes' => 'Extra delivery', 'source' => 'manual', 'user_id' => $this->admin->id]);
    $farmer = Beneficiary::factory()->create();
    $record = record($farmer, program('da', 'Certified Rice Seeds'), ['validation_status' => 'eligible', 'quantity' => 150]);
    app(ClaimService::class)->claim($record, $this->admin, ['quantity' => 150]);
    $count = InventoryMovement::count();

    $this->seed(InventorySeeder::class);
    $this->seed(InventorySeeder::class);

    expect(InventoryMovement::where('quantity', '<', 0)->count())->toBe(0)
        ->and(InventoryMovement::count())->toBeLessThanOrEqual($count)
        ->and($rice->fresh()->balance())->toBeGreaterThanOrEqual(0.0);
});

it('audits a program move on both items', function () {
    $rice = InventoryItem::where('name', 'Certified Rice Seeds')->sole();
    $program = program('da', 'Certified Rice Seeds');

    $this->actingAs($this->admin)->post(route('inventory.items.store'), itemInput(['name' => 'Hybrid Rice Seeds', 'interventions' => [$program->id]]))
        ->assertSessionHasNoErrors();

    $riceLog = AuditLog::where(['action' => 'Updated Inventory Item', 'auditable_id' => $rice->id])->sole();
    expect($riceLog->old_values['interventions'])->toContain('DA - Certified Rice Seeds')
        ->and($riceLog->new_values['interventions'])->not->toContain('DA - Certified Rice Seeds');
});
