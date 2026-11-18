<?php

use App\Models\User;
use App\Support\DashboardDetails;
use App\Support\DashboardOverview;
use App\Support\DashboardWidgets;
use Database\Seeders\DatabaseSeeder;

// User: "every element in the dashboard is interactive, like the one in Crisis Reports: click something and it shows
// further details. All roles." Figures are the sample records' (see DashboardOverviewTest).

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

$all = array_keys(DashboardWidgets::WIDGETS);

it('makes every tile and chart row a button that opens its details, for every role', function (string $username) {
    $user = User::where('username', $username)->sole();
    $widgets = DashboardWidgets::defaults($user);
    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

    foreach (['tile_farmers' => 'dash-farmers', 'tile_released' => 'dash-releases', 'tile_requests' => 'dash-requests', 'tile_low_stock' => 'dash-stock'] as $tile => $modal) {
        if (in_array($tile, $widgets, true)) {
            expect($html)->toContain('data-dash-tile="'.$modal.'"')->toContain('aria-label="'.match ($modal) {
                'dash-farmers' => 'Registered farmers', 'dash-releases' => 'Released in 2026-Q3',
                'dash-requests' => 'Pending assistance requests', 'dash-stock' => 'Stock items',
            }.'"');
        }
    }
    foreach (['chart_releases' => 'program', 'chart_barangays' => 'barangay', 'chart_stock' => 'item'] as $chart => $pick) {
        if (in_array($chart, $widgets, true)) {
            expect($html)->toContain('data-dash-pick="'.$pick.'"');
        }
    }
    expect($html)->toContain('View details');
})->with(['Admin_01', 'Agritech_02', 'Encoder_03']);

it('splits each program by barangay, adding up to the chart', function () use ($all) {
    $details = collect(DashboardDetails::build($all)['programs'])->keyBy('name');
    $chart = collect(DashboardOverview::build()['programs'])->keyBy('name');

    foreach ($chart as $name => $p) {
        expect($details[$name])->toMatchArray(['claimed' => $p['claimed'], 'waiting' => $p['waiting'], 'not_claimable' => $p['not_claimable'], 'total' => $p['total']])
            ->and(array_sum(array_column($details[$name]['barangays'], 'total')))->toBe($p['total']);
    }
});

it('lists every barangay with its farmers and crops', function () use ($all) {
    $barangays = collect(DashboardDetails::build($all)['barangays'])->keyBy('name');

    expect($barangays['Poblacion']['farmers'])->toBe(5)
        ->and(array_sum(array_column($barangays->all(), 'farmers')))->toBe(13)
        ->and(array_sum(array_column($barangays['Poblacion']['crops'], 'value')))->toBeLessThanOrEqual(5);
});

it('lists the pending requests and every stock item with its movements', function () use ($all) {
    $details = DashboardDetails::build($all);

    expect($details['requests']['total'])->toBe(9)
        ->and($details['requests']['rows'])->toHaveCount(9)
        ->and(collect($details['stock'])->where('low', true)->pluck('name')->all())->toContain('Certified Rice Seeds', 'Organic Liquid Fertilizer')
        ->and($details['stock'][0]['low'])->toBeTrue()   // low items first
        ->and(collect($details['stock'])->every(fn ($s) => count($s['movements']) <= DashboardDetails::MOVEMENTS))->toBeTrue();
});

it('works out only the windows of the widgets shown', function () {
    expect(DashboardDetails::build(['tile_requests']))->toHaveKeys(['requests'])->not->toHaveKeys(['barangays', 'programs', 'stock'])
        ->and(DashboardDetails::build([]))->toBe([]);
});
