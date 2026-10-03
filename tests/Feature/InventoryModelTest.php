<?php

use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Database\Seeders\DatabaseSeeder;

function stockOf(string $name): array
{
    $item = InventoryItem::withStock()->where('name', $name)->sole();

    return [$item->stockIn(), $item->stockOut(), $item->balance()];
}

it('seeds the Figma stock levels idempotently', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(InventoryItem::count())->toBe(4)
        ->and(stockOf('Certified Rice Seeds'))->toEqual([120.0, 86.0, 34.0])
        ->and(stockOf('Organic Liquid Fertilizer'))->toEqual([250.0, 210.0, 40.0])
        ->and(stockOf('Complete Fertilizer'))->toEqual([180.0, 150.0, 30.0])
        ->and(stockOf('HDPE Pipes'))->toEqual([500.0, 320.0, 180.0])
        ->and(InventoryItem::where('name', 'Certified Rice Seeds')->value('unit_label'))->toBe('sacks (20kg)');

    $juan = Beneficiary::where(['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])->sole()->interventionRecords()->sole();
    $auto = InventoryMovement::where(['intervention_record_id' => $juan->id, 'source' => 'auto'])->sole();
    expect($auto)->direction->toBe('out')->and((float) $auto->quantity)->toBe(2.0);
});

it('links stocked interventions to their items', function () {
    $this->seed(DatabaseSeeder::class);
    $fertilizer = InventoryItem::where('name', 'Complete Fertilizer')->value('id');

    expect(program('da', 'Complete Fertilizer')->inventory_item_id)->toBe($fertilizer)
        ->and(program('lgu', 'Complete Fertilizer')->inventory_item_id)->toBe($fertilizer)
        ->and(program('da', 'PAFF')->inventory_item_id)->toBeNull()
        ->and(program('da', 'HDPE Pipes')->unit)->toBe('meter')
        ->and(program('da', 'Certified Rice Seeds')->inventoryItem->name)->toBe('Certified Rice Seeds');
});

it('flags low items', function () {
    $this->seed(DatabaseSeeder::class);
    $item = fn (string $name) => InventoryItem::withStock()->where('name', $name)->sole();

    expect($item('Certified Rice Seeds')->isLow())->toBeTrue()     // 34 <= 40
        ->and($item('Organic Liquid Fertilizer')->isLow())->toBeTrue()
        ->and($item('Complete Fertilizer')->isLow())->toBeFalse()     // 30 > 25
        ->and($item('HDPE Pipes')->isLow())->toBeFalse();

    $none = InventoryItem::create(['name' => 'Molasses', 'unit' => 'liter', 'unit_label' => 'liters', 'low_stock_threshold' => 0]);
    expect($none->isLow())->toBeFalse();   // threshold 0 is never low
});

it('formats movement lines as in Figma', function () {
    $this->seed(DatabaseSeeder::class);
    $juanMove = InventoryMovement::where('source', 'auto')->orderBy('movement_date', 'desc')->first();
    $oil = InventoryItem::where('name', 'Organic Liquid Fertilizer')->sole();
    $in = new InventoryMovement(['inventory_item_id' => $oil->id, 'direction' => 'in', 'quantity' => 2.5]);

    expect($juanMove->signedLabel())->toBe('−2 sacks')
        ->and($in->signedLabel())->toBe('+2.5 liters')
        ->and($juanMove->line())->toBe('−2 sacks · Certified Rice Seeds · Auto-deducted: distribution to Juan Dela Cruz (RSBSA-0231) · Jul 18, 2026');
});

it('computes zero stock for an item without movements', function () {
    InventoryItem::create(['name' => 'Molasses', 'unit' => 'liter', 'unit_label' => 'liters', 'low_stock_threshold' => 10]);
    $item = InventoryItem::withStock()->sole();

    expect([$item->stockIn(), $item->stockOut(), $item->balance()])->toEqual([0.0, 0.0, 0.0])
        ->and(InventoryItem::sole()->balance())->toBe(0.0)          // without the eager sums
        ->and(InventoryItem::quantity(2.5))->toBe('2.5')
        ->and(InventoryItem::quantity(120.0))->toBe('120');
});
