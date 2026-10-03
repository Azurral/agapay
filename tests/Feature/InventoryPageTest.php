<?php

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
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
