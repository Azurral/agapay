<?php

use App\Models\InterventionRecord;
use App\Models\User;
use App\Support\DashboardOverview;
use Database\Seeders\DatabaseSeeder;

// The home page's data presentation (panel: "data visualization, easier digestion of information"): headline
// numbers and three charts, from the same records every other page uses. Figures below are the sample records'.

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('counts the headline numbers', function () {
    $tiles = DashboardOverview::build()['tiles'];

    expect($tiles['farmers'])->toBe(13)
        ->and($tiles['cycle'])->toBe('2026-Q3')
        ->and($tiles['claimed'])->toBe(1)          // Juan Dela Cruz's rice seeds
        ->and($tiles['assigned'])->toBe(5)         // archived records are left out
        ->and($tiles['pending_requests'])->toBe(9)
        ->and($tiles['low_stock'])->toBe(2);       // Certified Rice Seeds 34 <= 40, Organic Liquid Fertilizer 40 <= 50
});

it('splits each program of the current cycle into claimed, waiting and not claimable', function () {
    $programs = collect(DashboardOverview::build()['programs'])->keyBy('name');

    expect($programs['DA - Certified Rice Seeds'])->toMatchArray(['claimed' => 1, 'waiting' => 0, 'not_claimable' => 0])
        ->and($programs['DA - Complete Fertilizer'])->toMatchArray(['claimed' => 0, 'waiting' => 1, 'not_claimable' => 0])
        ->and($programs['LGU - Complete Fertilizer'])->toMatchArray(['claimed' => 0, 'waiting' => 0, 'not_claimable' => 1])   // Bedridden
        ->and($programs['LGU - Municipal Cash Subsidy'])->toMatchArray(['claimed' => 0, 'waiting' => 0, 'not_claimable' => 1]) // Duplicate
        ->and($programs['LGU - Emergency Seedlings'])->toMatchArray(['claimed' => 0, 'waiting' => 1, 'not_claimable' => 0]);
});

it('ranks barangays by registered farmers', function () {
    $barangays = DashboardOverview::build()['barangays'];

    expect(array_slice(array_column($barangays, 'name'), 0, 3))->toBe(['Poblacion', 'Bontoc Ili', 'Samoki'])
        ->and($barangays[0]['value'])->toBe(5)
        ->and($barangays[0]['percent'])->toBe(100.0);
});

it('shows each stock item against its low-stock level', function () {
    $stock = collect(DashboardOverview::build()['stock'])->keyBy('name');

    expect($stock['Certified Rice Seeds'])->toMatchArray(['balance' => 34.0, 'threshold' => 40.0, 'low' => true])
        ->and($stock['HDPE Pipes'])->toMatchArray(['balance' => 180.0, 'low' => false]);
});

it('puts each role\'s default overview on its home page', function (string $username, array $sees, array $hidden) {
    $response = $this->actingAs(User::where('username', $username)->sole())->get('/dashboard')->assertOk()
        ->assertSee('Overview')
        ->assertSee('Registered farmers')
        ->assertSee('Releases in 2026-Q3')
        ->assertSeeInOrder(['Claimed', 'Waiting', 'Not claimable']);   // the legend
    foreach ($sees as $text) {
        $response->assertSee($text);
    }
    foreach ($hidden as $text) {
        $response->assertDontSee($text);
    }
})->with([
    'administrator' => ['Admin_01', ['Farmers per barangay', 'low-stock level', 'Walk-in registrations waiting'], []],
    'agricultural technologist' => ['Agritech_02', ['Farmers per barangay', 'Latest crisis damage', 'Next claiming dates'], ['low-stock level']],
    'data encoder' => ['Encoder_03', ['low-stock level', 'Next claiming dates', 'Pending Encoding Queue'], ['Open Walk-in Approvals']],
]);

it('does not count a record forwarded to a later cycle as still waiting', function () {
    $carlos = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Carlos'))->sole();
    $carlos->forceFill(['forward_status' => 'forwarded'])->saveQuietly();

    expect(collect(DashboardOverview::build()['programs'])->keyBy('name')['DA - Complete Fertilizer'])
        ->toMatchArray(['claimed' => 0, 'waiting' => 0, 'not_claimable' => 1]);
});
