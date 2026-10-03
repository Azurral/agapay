<?php

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\ReportService;
use App\Support\ReportCriteria;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->cycle = DistributionCycle::where('code', '2026-Q3')->sole();
    $this->admin = User::where('username', 'Admin_01')->sole();
});

function buildReport(ReportCriteria $criteria): array
{
    return app(ReportService::class)->build($criteria);
}

it('totals records per intervention, barangay and beneficiary', function () {
    $data = buildReport(new ReportCriteria($this->cycle));
    $records = InterventionRecord::where('distribution_cycle_id', $this->cycle->id);

    expect($data['summary'])->toBe([
        'beneficiaries' => (clone $records)->distinct()->count('beneficiary_id'),
        'assigned' => (clone $records)->count(),
        'claimed' => (clone $records)->where('claim_status', 'claimed')->count(),
        'unclaimed' => (clone $records)->where('claim_status', 'unclaimed')->count(),
    ])
        ->and(array_sum(array_column($data['interventions'], 'assigned')))->toBe($data['summary']['assigned'])
        ->and(array_sum(array_column($data['barangays'], 'assigned')))->toBe($data['summary']['assigned'])
        ->and(array_sum(array_column($data['interventions'], 'claimed')))->toBe($data['summary']['claimed'])
        ->and(array_sum(array_column($data['barangays'], 'unclaimed')))->toBe($data['summary']['unclaimed'])
        ->and($data['beneficiaries'])->toHaveCount($data['summary']['assigned']);

    $row = $data['interventions'][0];
    expect($row)->toHaveKeys(['source', 'name', 'unit', 'assigned', 'claimed', 'unclaimed', 'quantity'])
        ->and($data['beneficiaries'][0])->toHaveKeys(['name', 'rsbsa', 'barangay', 'program', 'quantity', 'validation', 'claim', 'date'])
        ->and($data['criteria'])->toMatchArray(['cycle' => '2026-Q3 Dry Season', 'program' => 'All (DA & LGU)', 'dates' => 'All dates']);
});

it('sorts the beneficiary list by barangay then name', function () {
    $list = buildReport(new ReportCriteria($this->cycle))['beneficiaries'];

    expect(array_column($list, 'barangay'))->toBe(collect($list)->pluck('barangay')->sort()->values()->all());
});

it('filters by program', function () {
    $da = buildReport(new ReportCriteria($this->cycle, 'da'));
    $lgu = buildReport(new ReportCriteria($this->cycle, 'lgu'));
    $all = buildReport(new ReportCriteria($this->cycle));

    expect(collect($da['interventions'])->pluck('source')->unique()->all())->toBe(['DA'])
        ->and($da['summary']['assigned'] + $lgu['summary']['assigned'])->toBe($all['summary']['assigned'])
        ->and($da['criteria']['program'])->toBe('DA');
});

it('keeps unclaimed records and claimed ones inside the date range', function () {
    $claimed = InterventionRecord::where(['distribution_cycle_id' => $this->cycle->id, 'claim_status' => 'claimed'])->firstOrFail();
    $date = CarbonImmutable::parse($claimed->date_distributed);

    $inside = buildReport(new ReportCriteria($this->cycle, 'all', $date, $date));
    $outside = buildReport(new ReportCriteria($this->cycle, 'all', $date->addDay(), $date->addDays(2)));

    expect($inside['summary']['claimed'])->toBe(1)
        ->and($outside['summary']['claimed'])->toBe(0)
        ->and($outside['summary']['unclaimed'])->toBe($inside['summary']['unclaimed'])
        ->and($inside['criteria']['dates'])->toBe($date->format('M j, Y').' – '.$date->format('M j, Y'));
});

it('leaves archived records out', function () {
    $before = buildReport(new ReportCriteria($this->cycle))['summary']['assigned'];
    InterventionRecord::where('distribution_cycle_id', $this->cycle->id)->firstOrFail()->delete();

    expect(buildReport(new ReportCriteria($this->cycle))['summary']['assigned'])->toBe($before - 1);
});

it('reports inventory used from automatic stock movements', function () {
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
    $record = record($ana, program('da', 'Certified Rice Seeds'), ['distribution_cycle_id' => $this->cycle->id, 'quantity' => 3]);
    app(ClaimService::class)->claim($record, $this->admin, ['date_distributed' => today()->toDateString(), 'quantity' => 3], historical: true);

    $used = collect(buildReport(new ReportCriteria($this->cycle))['inventory'])->firstWhere('item', 'Certified Rice Seeds');
    expect($used)->not->toBeNull()->and($used['used'])->toBeGreaterThanOrEqual(3.0);

    $withClaim = $used['used'];
    app(ClaimService::class)->unclaim($record->fresh(), $this->admin);
    $after = collect(buildReport(new ReportCriteria($this->cycle))['inventory'])->firstWhere('item', 'Certified Rice Seeds');

    expect($after['used'] ?? 0.0)->toBe(round($withClaim - 3, 2));
});

it('reports quantity distributed from claimed records only', function () {
    $data = buildReport(new ReportCriteria($this->cycle));
    $expected = (float) InterventionRecord::where(['distribution_cycle_id' => $this->cycle->id, 'claim_status' => 'claimed'])->sum('quantity');

    expect(round(array_sum(array_column($data['interventions'], 'quantity')), 2))->toBe(round($expected, 2));
});

it('labels pending validation as a status, not an action', function () {
    $record = InterventionRecord::where(['distribution_cycle_id' => $this->cycle->id, 'validation_status' => 'pending'])->firstOrFail();
    $row = collect(buildReport(new ReportCriteria($this->cycle))['beneficiaries'])
        ->first(fn ($r) => $r['name'] === $record->beneficiary->fullName() && $r['program'] === $record->intervention->sourcedName());

    expect($row['validation'])->toBe('Pending Validation');
});

it('does not add up quantities of different units per barangay', function () {
    $barangays = buildReport(new ReportCriteria($this->cycle))['barangays'];

    expect($barangays[0])->toHaveKeys(['name', 'beneficiaries', 'assigned', 'claimed', 'unclaimed'])->not->toHaveKey('quantity');
});

it('leaves records of archived farmers out like the working lists', function () {
    $before = buildReport(new ReportCriteria($this->cycle))['summary']['assigned'];
    $record = InterventionRecord::where('distribution_cycle_id', $this->cycle->id)->firstOrFail();
    $count = InterventionRecord::where(['distribution_cycle_id' => $this->cycle->id, 'beneficiary_id' => $record->beneficiary_id])->count();
    $record->beneficiary->delete();

    expect(buildReport(new ReportCriteria($this->cycle))['summary']['assigned'])->toBe($before - $count);
});
