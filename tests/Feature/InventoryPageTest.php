<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->rice = InventoryItem::where('name', 'Certified Rice Seeds')->sole();
});

function movementInput(array $overrides = []): array
{
    return [
        'inventory_item_id' => InventoryItem::where('name', 'Certified Rice Seeds')->value('id'),
        'direction' => 'in', 'quantity' => '50', 'movement_date' => today()->toDateString(), 'notes' => 'Delivery from DA-RFO',
        ...$overrides,
    ];
}

it('shows Figma stock columns for the administrator', function () {
    $this->actingAs($this->admin)->get('/inventory')->assertOk()
        ->assertSee('INVENTORY MANAGEMENT')
        ->assertSee('Inventory Monitoring')
        ->assertSeeInOrder(['Unit', 'Stock-In', 'Stock-Out', 'Balance', '+ Record Movement'])
        ->assertSeeInOrder(['Certified Rice Seeds', 'sacks (20kg)', '120', '86', '34'])
        ->assertSeeInOrder(['HDPE Pipes', 'meters', '500', '320', '180'])
        ->assertSee('Recent Movements')
        ->assertSee('−2 sacks · Certified Rice Seeds · Auto-deducted: distribution to Juan Dela Cruz (RSBSA-0231) · Jul 18, 2026')
        ->assertSee('AUTO')
        ->assertSee('Stock-out from confirmed distributions is recorded automatically. Use + Record Movement for adjustments, deliveries, and manual corrections.');
});

it('titles the page for the encoder', function () {
    $this->actingAs($this->encoder)->get('/inventory')->assertOk()->assertSee('INVENTORY MONITORING')->assertSee('+ Record Movement');
});

it('marks low stock items', function () {
    $this->actingAs($this->admin)->get('/inventory')
        ->assertSee('title="Low stock (threshold 40)"', false)
        ->assertDontSee('title="Low stock (threshold 100)"', false);
});

it('searches items as plain text', function () {
    $this->actingAs($this->admin)->get('/inventory?q=fert')
        ->assertSee('>Organic Liquid Fertilizer</span>', false)->assertSee('>Complete Fertilizer</span>', false)
        ->assertDontSee('>HDPE Pipes</span>', false);   // still named in the modal's item list and Recent Movements
    $this->actingAs($this->admin)->get('/inventory?q=zzz')->assertSee('No items match your search.');
});

it('treats hostile search input as plain text', function (string $query) {
    $this->actingAs($this->admin)->get('/inventory?'.$query)->assertOk();
})->with(['q[]=x', 'q=%25', 'q=_', 'q='.str_repeat('a', 300)]);

it('records a movement from the modal', function () {
    $this->actingAs($this->encoder)->from('/inventory')->post('/inventory/movements', movementInput())
        ->assertRedirect('/inventory')->assertSessionHas('status', '+50 sacks · Certified Rice Seeds recorded.');

    expect($this->rice->balance())->toBe(84.0)
        ->and(AuditLog::where('action', 'Recorded Stock In')->exists())->toBeTrue();

    $this->get('/inventory')->assertSee('+50 sacks · Certified Rice Seeds · Delivery from DA-RFO · '.today()->format('M j, Y'))->assertSee('MANUAL');
});

it('refuses a stock-out above the balance with a field error', function () {
    $this->actingAs($this->admin)->post('/inventory/movements', movementInput(['direction' => 'out', 'quantity' => 35]))
        ->assertSessionHasErrorsIn('inventory', ['quantity' => 'Not enough stock: Certified Rice Seeds has 34 sacks left, 35 needed.']);

    expect($this->rice->balance())->toBe(34.0);
});

it('validates the modal fields', function (array $overrides, string $field) {
    $this->actingAs($this->admin)->post('/inventory/movements', movementInput($overrides))
        ->assertSessionHasErrorsIn('inventory', $field);
})->with([
    'unknown item' => [['inventory_item_id' => 99999], 'inventory_item_id'],
    'array item' => [['inventory_item_id' => [1]], 'inventory_item_id'],
    'no direction' => [['direction' => ''], 'direction'],
    'zero quantity' => [['quantity' => '0'], 'quantity'],
    'future date' => [['movement_date' => '2999-01-01'], 'movement_date'],
    'long notes' => [['notes' => str_repeat('a', 300)], 'notes'],
]);

it('keeps inventory away from agri techs', function () {
    $this->actingAs($this->agritech)->get('/inventory')->assertForbidden();
    $this->actingAs($this->agritech)->post('/inventory/movements', movementInput())->assertForbidden();
});

it('renders without movements', function () {
    InventoryMovement::query()->delete();

    $this->actingAs($this->admin)->get('/inventory')->assertOk()
        ->assertSee('No stock movements yet.')
        ->assertSeeInOrder(['Certified Rice Seeds', 'sacks (20kg)', '0', '0', '0']);
});

it('adds an item and links interventions so claims deduct it', function () {
    $molasses = program('da', 'Molasses');

    $this->actingAs($this->encoder)->post('/inventory/items', [
        'name' => 'Molasses', 'unit' => 'liter', 'unit_label' => 'liters', 'low_stock_threshold' => '10', 'interventions' => [$molasses->id],
    ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Molasses saved.');

    $item = InventoryItem::where('name', 'Molasses')->sole();
    expect($molasses->fresh()->inventory_item_id)->toBe($item->id)
        ->and(AuditLog::where('action', 'Added Inventory Item')->value('record_label'))->toBe('Molasses');

    app(InventoryService::class)->record($item, 'in', 100, today()->toDateString(), null, $this->admin);
    $juan = Beneficiary::where(['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])->sole();
    $record = record($juan, $molasses, ['validation_status' => 'eligible', 'quantity' => 3]);
    app(ClaimService::class)->claim($record, $this->admin);

    expect($item->balance())->toBe(97.0);
});

it('updates a threshold and the low marker follows', function () {
    $this->actingAs($this->admin)->put(route('inventory.items.update', $this->rice), [
        'name' => 'Certified Rice Seeds', 'unit' => 'sack', 'unit_label' => 'sacks (20kg)', 'low_stock_threshold' => '30',
        'interventions' => [program('da', 'Certified Rice Seeds')->id],
    ])->assertSessionHasNoErrors();

    expect($this->rice->fresh()->isLow())->toBeFalse()
        ->and(AuditLog::where('action', 'Updated Inventory Item')->exists())->toBeTrue();
    $this->actingAs($this->admin)->get('/inventory')->assertDontSee('title="Low stock (threshold 40)"', false);
});

it('refuses duplicate or incomplete items', function (array $input, string $field) {
    $this->actingAs($this->admin)->post('/inventory/items', [
        'name' => 'Seedling Trays', 'unit' => 'tray', 'unit_label' => 'trays', 'low_stock_threshold' => '5', ...$input,
    ])->assertSessionHasErrorsIn('item', $field);
})->with([
    'duplicate ignoring case' => [['name' => '  certified RICE seeds '], 'name'],
    'no name' => [['name' => ''], 'name'],
    'no unit' => [['unit' => ''], 'unit'],
    'negative threshold' => [['low_stock_threshold' => '-1'], 'low_stock_threshold'],
    'unknown intervention' => [['interventions' => [99999]], 'interventions.0'],
]);

it('moves intervention links between items and unlinks the ones left out', function () {
    $fertilizer = InventoryItem::where('name', 'Complete Fertilizer')->sole();

    $this->actingAs($this->admin)->post('/inventory/items', [
        'name' => 'Complete Fertilizer 14-14-14', 'unit' => 'sack', 'unit_label' => 'sacks (50kg)', 'low_stock_threshold' => '0',
        'interventions' => [program('lgu', 'Complete Fertilizer')->id],
    ])->assertSessionHasNoErrors();
    expect(program('lgu', 'Complete Fertilizer')->inventory_item_id)->not->toBe($fertilizer->id)
        ->and(program('da', 'Complete Fertilizer')->inventory_item_id)->toBe($fertilizer->id);

    $this->actingAs($this->admin)->put(route('inventory.items.update', $fertilizer), [
        'name' => 'Complete Fertilizer', 'unit' => 'sack', 'unit_label' => 'sacks (50kg)', 'low_stock_threshold' => '25', 'interventions' => [],
    ])->assertSessionHasNoErrors();
    expect(program('da', 'Complete Fertilizer')->inventory_item_id)->toBeNull();
});

it('shows the manage items modal to managers only', function () {
    $this->actingAs($this->admin)->get('/inventory')->assertSee('Manage Items')->assertSee('Manage Inventory Items');
    $this->actingAs($this->agritech)->post('/inventory/items', ['name' => 'X'])->assertForbidden();
});

it('lists a back-dated movement first in Recent Movements', function () {
    $this->actingAs($this->admin)->post('/inventory/movements', movementInput(['movement_date' => '2026-01-15', 'notes' => 'Late encoding of January delivery']));

    $html = $this->actingAs($this->admin)->get('/inventory')->getContent();
    $recent = substr($html, strpos($html, 'Recent Movements'));
    $late = strpos($recent, 'Late encoding of January delivery');
    expect($late)->not->toBeFalse()
        ->and($late)->toBeLessThan(strpos($recent, 'Auto-deducted: distribution to Juan Dela Cruz'));
});
