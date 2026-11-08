<?php

use App\Exceptions\InsufficientStock;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Services\ClaimService;
use App\Services\InterventionAssignment;
use App\Services\InventoryService;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\InterventionSeeder;

// Basis path testing of InventoryService::syncRecord (manuscript section 3.4.2), which keeps a record's automatic
// stock movements equal to what it should have deducted. Decisions:
//   P1 record still deducts (claimed, not archived, quantity above 0)?  P2 already deducted from an item?
//   P3 an item to deduct from?  P4 another item to settle (loop)?  P5 more to deduct?  P6 too much deducted?
//   P7 not enough stock?
// V(G) = 7 + 1 = 8 independent paths.

beforeEach(function () {
    seedRoles();
    $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
    $this->admin = userWithRole(Role::ADMIN);
    $this->stock = app(InventoryService::class);
    $this->claims = app(ClaimService::class);

    $this->item = InventoryItem::create(['name' => 'Certified Rice Seeds', 'unit' => 'sack', 'unit_label' => 'sacks', 'low_stock_threshold' => 0]);
    $this->seeds = program('da', 'Certified Rice Seeds');
    $this->seeds->update(['inventory_item_id' => $this->item->id]);
    $this->stock->record($this->item, 'in', 10, today()->toDateString(), 'Starting stock', $this->admin);
    $this->balance = fn (?InventoryItem $item = null) => ($item ?? $this->item)->fresh()->balance();
});

it('path 1: P1 true, P2 false, P3 true, P5 true, P7 false - a claim deducts its quantity', function () {
    $this->claims->claim(record(Beneficiary::factory()->create(), $this->seeds, ['quantity' => 3]), $this->admin);

    expect(($this->balance)())->toBe(7.0);
});

it('path 2: P5 true, P7 true - not enough stock: nothing is deducted', function () {
    $record = record(Beneficiary::factory()->create(), $this->seeds, ['quantity' => 11]);

    expect(fn () => $this->claims->claim($record, $this->admin))->toThrow(InsufficientStock::class, 'Not enough stock: Certified Rice Seeds has 10 sacks left, 11 needed.')
        ->and(($this->balance)())->toBe(10.0);
});

it('path 3: P1 false, P3 false, P6 true - unclaiming returns the stock', function () {
    $record = $this->claims->claim(record(Beneficiary::factory()->create(), $this->seeds, ['quantity' => 3]), $this->admin);
    $this->claims->unclaim($record, $this->admin);

    expect(($this->balance)())->toBe(10.0);
});

it('path 4: P2 true, P5 false, P6 false - syncing again changes nothing', function () {
    $record = $this->claims->claim(record(Beneficiary::factory()->create(), $this->seeds, ['quantity' => 3]), $this->admin);
    $this->stock->syncRecord($record->fresh(), $this->admin);

    expect(($this->balance)())->toBe(7.0)
        ->and(InventoryMovement::where(['intervention_record_id' => $record->id, 'source' => InventoryMovement::AUTO])->count())->toBe(1);
});

it('path 5: P1 true, P2 false, P3 false, P4 false - a program without stock moves nothing', function () {
    $this->claims->claim(record(Beneficiary::factory()->create(), program('da', 'PAFF'), ['quantity' => 1]), $this->admin);

    expect(($this->balance)())->toBe(10.0);
});

it('path 6: P2 true, P5 true - a larger quantity deducts only the difference', function () {
    $record = $this->claims->claim(record(Beneficiary::factory()->create(), $this->seeds, ['quantity' => 3]), $this->admin);
    app(InterventionAssignment::class)->reassign($record, ['quantity' => 5], $this->admin);

    expect(($this->balance)())->toBe(5.0);
});

it('path 7: P2 true, P6 true - a smaller quantity returns the difference', function () {
    $record = $this->claims->claim(record(Beneficiary::factory()->create(), $this->seeds, ['quantity' => 3]), $this->admin);
    app(InterventionAssignment::class)->reassign($record, ['quantity' => 1], $this->admin);

    expect(($this->balance)())->toBe(9.0);
});

it('path 8: P1 false, P3 false, P4 false - an unclaimed record that never deducted moves nothing', function () {
    $record = record(Beneficiary::factory()->create(), $this->seeds, ['quantity' => 3]);
    app(InterventionAssignment::class)->reassign($record, ['quantity' => 4], $this->admin);

    expect(($this->balance)())->toBe(10.0)
        ->and(InventoryMovement::where('intervention_record_id', $record->id)->exists())->toBeFalse();
});
