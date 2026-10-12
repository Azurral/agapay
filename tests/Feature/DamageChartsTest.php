<?php

use App\Http\Controllers\DamageExportController;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use App\Support\DamageCharts;
use App\Support\DamageReportFilters;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->filters = fn (array $query = []) => DamageReportFilters::fromRequest(Request::create('/damage-reports', 'GET', $query)->setUserResolver(fn () => $this->admin));
});

it('totals the cost of damage per barangay and per crop, largest first', function () {
    $charts = DamageCharts::build(($this->filters)()->query());

    $byBarangay = DamageReport::query()->get()->groupBy(fn ($r) => $r->barangay->name)->map->sum('cost')->sortDesc();
    expect(array_column($charts['barangays'], 'name'))->toBe($byBarangay->keys()->take(DamageCharts::BARS)->values()->all())
        ->and(array_sum(array_column($charts['barangays'], 'value')))->toEqualWithDelta((float) DamageReport::sum('cost'), 0.01)
        ->and(array_sum(array_column($charts['crops'], 'value')))->toEqualWithDelta((float) DamageReport::sum('cost'), 0.01)
        ->and($charts['barangays'][0]['percent'])->toBe(100.0);
});

it('follows the page filters', function () {
    $samoki = Barangay::where('name', 'Samoki')->sole();
    $charts = DamageCharts::build(($this->filters)(['barangay' => (string) $samoki->id])->query());

    expect(array_column($charts['barangays'], 'name'))->toBe(['Samoki']);
});

it('draws both charts on the Crisis Reports page', function () {
    $html = $this->actingAs($this->admin)->get('/damage-reports')->assertOk()
        ->assertSeeInOrder(['Est. Cost of Damage', 'Cost of Damage by Barangay', 'Cost of Damage by Crop', 'Reported Damage Records'])
        ->getContent();

    expect(substr_count($html, 'class="damage-bar'))->toBeGreaterThan(2);
});

it('says so when no report matches instead of drawing empty charts', function () {
    $none = Disaster::create(['name' => 'Hailstorm', 'occurred_on' => '2026-01-01']);

    $this->actingAs($this->admin)->get('/damage-reports?disaster='.$none->id)->assertOk()
        ->assertSee('No damage to chart for these filters.');
});

it('prints the same charts in the damage PDF', function () {
    $html = view('damage.pdf', DamageExportController::pdfData(($this->filters)(), $this->admin))->render();

    expect($html)->toContain('Cost of Damage by Barangay')->toContain('Cost of Damage by Crop')->toContain('class="pdf-bar"');
});
