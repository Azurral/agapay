<?php

use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use App\Support\DamageBreakdown;
use App\Support\DamageReportFilters;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;

// Panel (Ma'am Libby): the summary cards of Crisis Reports open the data behind them, and look like buttons.

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->typhoon = Disaster::where('name', 'Typhoon Cristina')->sole();
});

function breakdownFor(int $disasterId): array
{
    $request = Request::create('/damage-reports', 'GET', ['disaster' => $disasterId]);
    $request->setUserResolver(fn () => User::where('username', 'Admin_01')->sole());

    return DamageBreakdown::build(DamageReportFilters::fromRequest($request)->query());
}

it('makes every summary card a button that opens its details', function () {
    $this->actingAs($this->admin)->get('/damage-reports?disaster='.$this->typhoon->id)->assertOk()
        ->assertSee('aria-haspopup="dialog"', false)
        ->assertSee('View details')
        ->assertSeeInOrder(['Farmers affected', 'Area damaged', 'Production loss', 'Estimated cost of damage']);
});

it('lists the affected farmers behind the card', function () {
    $farmers = breakdownFor($this->typhoon->id)['farmers'];

    expect(array_column($farmers, 'name'))->toContain('Juan Dela Cruz', 'Maria Santos', 'Carlos Ibanez', 'Rosa Mendez', 'Lorna Reyes', 'Liza Domingo')
        ->and($farmers)->toHaveCount(6);
});

it('adds up to the same totals as the cards', function () {
    $b = breakdownFor($this->typhoon->id);

    // Sample reports: total 1.2 + 0.8 + 1.5 + 0.6 + 1.0 + 0.9 = 6.0 ha, partial 0.3 + 0.2 + 0 + 0.1 + 0.4 + 0 = 1.0 ha.
    expect(round(array_sum(array_column($b['area_by_barangay'], 'total')), 2))->toBe(6.0)
        ->and(round(array_sum(array_column($b['area_by_barangay'], 'partial')), 2))->toBe(1.0)
        ->and(round(array_sum(array_column($b['loss_by_crop'], 'value')), 2))->toBe(round((float) DamageReport::where('disaster_id', $this->typhoon->id)->sum('loss_mt'), 2))
        ->and(round(array_sum(array_column($b['cost_by_barangay'], 'value')), 2))->toBe(round((float) DamageReport::where('disaster_id', $this->typhoon->id)->sum('cost'), 2));
});

it('splits production loss by crop with the yield used', function () {
    $rice = collect(breakdownFor($this->typhoon->id)['loss_by_crop'])->firstWhere('name', 'Rice');

    expect($rice['farmers'])->toBe(3)->and($rice['yield'])->toBe(4.0);
});

it('lets a barangay or crop bar open the farmers affected there', function () {
    $html = $this->actingAs($this->admin)->get('/damage-reports?disaster='.$this->typhoon->id)->assertOk()
        ->assertSee('data-damage-filter="barangay"', false)
        ->assertSee('data-damage-filter="crop"', false)
        ->assertSee('Click a row to see the farmers')
        ->assertSee('data-farmer-row', false)
        ->getContent();

    expect(substr_count($html, 'data-farmer-row'))->toBe(6)
        ->and($html)->toContain('width: calc(100vw - 80px)');   // the details windows use the whole width
});
