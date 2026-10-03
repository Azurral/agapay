<?php

use App\Exceptions\InsufficientStock;
use App\Models\AuditLog;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->inventory = app(InventoryService::class);
    $this->item = fn (string $name) => InventoryItem::where('name', $name)->sole();
    $this->carlos = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Carlos'))->sole();   // 1 sack Complete Fertilizer
});

function balanceOf(string $name): float
{
    return InventoryItem::where('name', $name)->sole()->balance();
}

it('records deliveries and manual stock-out', function () {
    $this->inventory->record(($this->item)('Certified Rice Seeds'), 'in', '50', today()->toDateString(), 'Delivery from DA-RFO', $this->admin);
    $this->inventory->record(($this->item)('Certified Rice Seeds'), 'out', '4', today()->toDateString(), null, $this->admin);

    expect(balanceOf('Certified Rice Seeds'))->toBe(80.0)
        ->and(AuditLog::where('action', 'Recorded Stock In')->value('record_label'))->toBe('Certified Rice Seeds')
        ->and(AuditLog::where('action', 'Recorded Stock Out')->exists())->toBeTrue()
        ->and(InventoryMovement::latest('id')->first())->source->toBe('manual')->user_id->toBe($this->admin->id);
});

it('refuses a stock-out above the balance', function () {
    $before = InventoryMovement::count();

    expect(fn () => $this->inventory->record(($this->item)('Complete Fertilizer'), 'out', 31, today()->toDateString(), null, $this->admin))
        ->toThrow(InsufficientStock::class, 'Not enough stock: Complete Fertilizer has 30 sacks left, 31 needed.');
    expect(InventoryMovement::count())->toBe($before);
});

it('validates manual movements', function (string $direction, mixed $quantity, ?string $date) {
    expect(fn () => $this->inventory->record(($this->item)('HDPE Pipes'), $direction, $quantity, $date, null, $this->admin))
        ->toThrow(ValidationException::class);
})->with([
    'zero' => ['in', 0, '2026-07-01'],
    'negative' => ['in', -1, '2026-07-01'],
    'huge' => ['in', 100000, '2026-07-01'],
    'three decimals' => ['in', 1.234, '2026-07-01'],
    'text' => ['in', 'abc', '2026-07-01'],
    'future date' => ['in', 5, '2999-01-01'],
    'no date' => ['in', 5, null],
    'bad direction' => ['sideways', 5, '2026-07-01'],
]);

it('deducts a claimed record and returns it when unclaimed', function () {
    $this->carlos->forceFill(['validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-07-20'])->saveQuietly();

    $this->inventory->syncRecord($this->carlos, $this->admin);
    $this->inventory->syncRecord($this->carlos, $this->admin);   // idempotent
    expect(balanceOf('Complete Fertilizer'))->toBe(29.0);

    $out = InventoryMovement::where(['intervention_record_id' => $this->carlos->id, 'direction' => 'out'])->sole();
    expect($out)->source->toBe('auto')->notes->toBe('Auto-deducted: distribution to Carlos Ibanez (RSBSA-0099)')
        ->and($out->movement_date->toDateString())->toBe('2026-07-20');

    $this->carlos->forceFill(['claim_status' => 'unclaimed'])->saveQuietly();
    $this->inventory->syncRecord($this->carlos, $this->admin, 'unclaimed');

    expect(balanceOf('Complete Fertilizer'))->toBe(30.0)
        ->and(InventoryMovement::where(['intervention_record_id' => $this->carlos->id, 'direction' => 'in'])->value('notes'))
        ->toBe('Returned: unclaimed distribution to Carlos Ibanez (RSBSA-0099)');
});

it('adjusts by the difference when the quantity changes', function () {
    $this->carlos->forceFill(['claim_status' => 'claimed', 'quantity' => 2])->saveQuietly();
    $this->inventory->syncRecord($this->carlos, $this->admin);
    expect(balanceOf('Complete Fertilizer'))->toBe(28.0);

    $this->carlos->forceFill(['quantity' => 5])->saveQuietly();
    $this->inventory->syncRecord($this->carlos, $this->admin);
    expect(balanceOf('Complete Fertilizer'))->toBe(25.0);

    $this->carlos->forceFill(['quantity' => 1])->saveQuietly();
    $this->inventory->syncRecord($this->carlos, $this->admin, 'quantity reduced');
    expect(balanceOf('Complete Fertilizer'))->toBe(29.0)
        ->and(InventoryMovement::where('direction', 'in')->latest('id')->value('notes'))->toBe('Returned: quantity reduced distribution to Carlos Ibanez (RSBSA-0099)');
});

it('blocks a deduction larger than the balance', function () {
    $this->carlos->forceFill(['claim_status' => 'claimed', 'quantity' => 31])->saveQuietly();

    expect(fn () => $this->inventory->syncRecord($this->carlos, $this->admin))
        ->toThrow(InsufficientStock::class, 'Not enough stock: Complete Fertilizer has 30 sacks left, 31 needed.');
    expect(InventoryMovement::where('intervention_record_id', $this->carlos->id)->exists())->toBeFalse();
});

it('returns the stock of an archived claimed record', function () {
    $this->carlos->forceFill(['claim_status' => 'claimed'])->saveQuietly();
    $this->inventory->syncRecord($this->carlos, $this->admin);
    $this->carlos->delete();

    $this->inventory->syncRecord($this->carlos, $this->admin, 'archived');
    expect(balanceOf('Complete Fertilizer'))->toBe(30.0);
});

it('ignores unlinked programs and records without quantity', function () {
    $liza = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Liza'))->sole();   // PAFF, cash aid
    $liza->forceFill(['claim_status' => 'claimed', 'quantity' => 5000])->saveQuietly();
    $this->carlos->forceFill(['claim_status' => 'claimed', 'quantity' => null])->saveQuietly();
    $before = InventoryMovement::count();

    $this->inventory->syncRecord($liza, $this->admin);
    $this->inventory->syncRecord($this->carlos, $this->admin);

    expect(InventoryMovement::count())->toBe($before);
});

it('counts low stock items', function () {
    expect($this->inventory->lowStockCount())->toBe(2);

    $this->inventory->record(($this->item)('Certified Rice Seeds'), 'in', 20, today()->toDateString(), null, $this->admin);
    expect($this->inventory->lowStockCount())->toBe(1);
});
