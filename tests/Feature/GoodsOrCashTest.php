<?php

use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

// Panel: "Where are the specific given fertilizers - 2 sacks? 1 sack? Must have a filter for goods or cash."

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
});

it('marks each program as goods or cash', function () {
    expect(program('lgu', 'Municipal Cash Subsidy')->kind)->toBe('cash')
        ->and(program('da', 'RFFA')->kind)->toBe('cash')
        ->and(program('da', 'PAFF')->kind)->toBe('cash')
        ->and(program('da', 'Certified Rice Seeds')->kind)->toBe('goods');
});

it('shows a cash amount in pesos and goods in their unit', function () {
    $cash = InterventionRecord::factory()->create(['intervention_id' => program('lgu', 'Municipal Cash Subsidy')->id, 'quantity' => 5000]);
    $seeds = InterventionRecord::factory()->create(['intervention_id' => program('da', 'Certified Rice Seeds')->id, 'quantity' => 2]);

    expect($cash->quantityDisplay())->toBe('₱5,000')
        ->and($seeds->quantityDisplay())->toBe('2 sacks');
});

it('filters the lists to goods or cash', function () {
    $this->actingAs($this->admin);

    $this->get('/interventions/da?intervention=goods&show=all')->assertOk()
        ->assertSee('Juan Dela Cruz')->assertDontSee('Liza Domingo');      // Liza: PAFF (cash)
    $this->get('/interventions/da?intervention=cash&show=all')->assertOk()
        ->assertSee('Liza Domingo')->assertDontSee('Juan Dela Cruz');      // Juan: rice seeds (goods)
    $this->get('/interventions/da')->assertSee('All goods')->assertSee('All cash');
});

it('sets the type on the program form', function () {
    $this->actingAs($this->admin)->post(route('programs.store'), [
        'source' => 'lgu', 'name' => 'Fuel Subsidy', 'unit' => '', 'kind' => 'cash',
    ])->assertSessionHasNoErrors();

    expect(Intervention::where('name', 'Fuel Subsidy')->value('kind'))->toBe('cash');
});
