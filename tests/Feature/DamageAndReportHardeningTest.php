<?php

use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Services\ReportService;
use App\Support\MemoryLimit;
use App\Support\ReportCriteria;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
});

function hardenedDamageInput(array $overrides = []): array
{
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();

    return [
        'disaster_id' => Disaster::where('name', 'Southwest Monsoon Flooding')->value('id'), 'beneficiary_id' => $ana->id,
        'barangay_id' => $ana->barangay_id, 'crop_id' => Crop::where('name', 'Rice')->value('id'), 'crop_stage' => 'vegetative',
        'total_area_ha' => '1', 'partial_area_ha' => '0', 'photos' => [UploadedFile::fake()->image('field.jpg')], ...$overrides,
    ];
}

it('refuses a cost larger than the column', function () {
    Crop::where('name', 'Rice')->update(['yield_mt_per_ha' => 999.99, 'price_per_mt' => 9999999.99]);

    $this->actingAs($this->encoder)->post('/damage-reports', hardenedDamageInput(['total_area_ha' => '9999', 'partial_area_ha' => '9999']))
        ->assertSessionHasErrors(['total_area_ha' => 'The computed cost is too large — check the crop values.']);
});

it('leaves no photos and a clear message when a save deadlocks', function () {
    $failed = false;
    DamagePhoto::created(function () use (&$failed) {
        if (! $failed) {
            $failed = true;
            throw new QueryException('mysql', 'insert', [], new PDOException('Deadlock found when trying to get lock', 40001));
        }
    });

    // Under the test's wrapping transaction Laravel cannot retry, so the deadlock reaches the controller.
    // (Its savepoint is not rolled back either, so only the files and the message can be checked here.)
    $this->actingAs($this->encoder)->from('/damage-reports/create')->post('/damage-reports', hardenedDamageInput(['photos' => [UploadedFile::fake()->image('a.jpg')]]))
        ->assertRedirect('/damage-reports/create')
        ->assertSessionHasErrors(['report' => 'Another save happened at the same moment. Please submit the report again.']);

    expect(Storage::disk('local')->allFiles('damage'))->toBe([]);
});

it('redisplays the damage form after forged input', function (array $overrides) {
    $this->actingAs($this->encoder)->from('/damage-reports/create')->followingRedirects()
        ->post('/damage-reports', hardenedDamageInput($overrides))->assertOk()->assertSee('New Damage Report');
})->with([
    'array farmer' => [['beneficiary_id' => ['1']]],
    'array crop' => [['crop_id' => ['1']]],
    'array area' => [['total_area_ha' => ['1']]],
    'array location' => [['farm_location' => ['x']]],
]);

it('redisplays the reports form after forged input', function (array $input) {
    $this->actingAs($this->encoder)->from('/reports')->followingRedirects()
        ->post('/reports', ['program' => 'all', 'format' => 'pdf', ...$input])->assertOk()->assertSee('Generate Report');
})->with([
    'array start' => [['start_date' => ['x'], 'distribution_cycle_id' => 1]],
    'array cycle' => [['distribution_cycle_id' => ['1']]],
]);

it('links the existing report on a duplicate', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', hardenedDamageInput());
    $existing = DamageReport::latest('id')->firstOrFail();

    $this->from('/damage-reports/create')->followingRedirects()->post('/damage-reports', hardenedDamageInput())
        ->assertSee('already has a Rice damage report')
        ->assertSee(route('damage.show', $existing), false);
});

it('shows archived reports with a neutral chip', function () {
    $report = DamageReport::where('status', DamageReport::VALIDATED)->firstOrFail();
    $report->delete();

    $html = view('components.damage.status', ['report' => $report->fresh() ?? DamageReport::withTrashed()->find($report->id)])->render();

    expect($html)->toContain('Archived')->toContain('border-field')->not->toContain('border-ok');
});

it('never lowers a higher memory limit', function () {
    $original = ini_get('memory_limit');

    ini_set('memory_limit', '1G');
    MemoryLimit::atLeast('512M');
    expect(ini_get('memory_limit'))->toBe('1G');

    ini_set('memory_limit', '256M');
    MemoryLimit::atLeast('512M');
    expect(ini_get('memory_limit'))->toBe('512M');

    ini_set('memory_limit', '-1');
    MemoryLimit::atLeast('512M');
    expect(ini_get('memory_limit'))->toBe('-1');

    ini_set('memory_limit', $original);
});

it('separates records that cannot be claimed', function () {
    $cycle = DistributionCycle::current();
    record($this->ana, program('lgu', 'Emergency Seedlings'), ['distribution_cycle_id' => $cycle->id, 'validation_status' => 'relocated']);

    $data = app(ReportService::class)->build(new ReportCriteria($cycle));
    $notClaimable = InterventionRecord::where('distribution_cycle_id', $cycle->id)->where('claim_status', 'unclaimed')
        ->whereIn('validation_status', ['duplicate', 'relocated', 'inactive'])->count();

    expect($data['summary']['not_claimable'])->toBe($notClaimable)
        ->and($data['summary']['assigned'])->toBe($data['summary']['claimed'] + $data['summary']['unclaimed'] + $data['summary']['not_claimable'])
        ->and(array_sum(array_column($data['interventions'], 'not_claimable')))->toBe($notClaimable)
        ->and(array_sum(array_column($data['barangays'], 'not_claimable')))->toBe($notClaimable);
});

it('sorts the beneficiary list by barangay, last and first name', function () {
    $list = app(ReportService::class)->build(new ReportCriteria(DistributionCycle::current()))['beneficiaries'];
    $keys = array_map(function (array $row) {
        $person = Beneficiary::withTrashed()->get()->first(fn ($b) => $b->fullName() === $row['name']);

        return mb_strtolower($row['barangay'].'|'.$person->last_name.'|'.$person->first_name.'|'.$row['program']);
    }, $list);
    $sorted = $keys;
    sort($sorted);

    expect($keys)->toBe($sorted);
});

it('only reports on distribution cycles', function () {
    expect(fn () => new ReportCriteria(null))->toThrow(TypeError::class);

    $this->actingAs($this->admin)->post('/reports', ['program' => 'all', 'format' => 'pdf'])
        ->assertSessionHasErrors(['distribution_cycle_id' => 'Choose a distribution cycle.']);
});

it('labels records that cannot be claimed in the beneficiary list', function () {
    $cycle = DistributionCycle::current();
    record($this->ana, program('lgu', 'Emergency Seedlings'), ['distribution_cycle_id' => $cycle->id, 'validation_status' => 'relocated']);

    $row = collect(app(ReportService::class)->build(new ReportCriteria($cycle))['beneficiaries'])
        ->first(fn ($r) => $r['name'] === 'Ana Dela Cruz' && str_contains($r['program'], 'Emergency Seedlings'));

    expect($row['claim'])->toBe('Not Claimable');
});
