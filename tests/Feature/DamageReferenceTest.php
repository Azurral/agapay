<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
});

/** Every crop's current values in the shape the modal submits. */
function cropValues(array $changes = []): array
{
    return Crop::all()->mapWithKeys(fn (Crop $crop) => [$crop->id => [
        'yield_mt_per_ha' => (string) $crop->yield_mt_per_ha,
        'price_per_mt' => (string) $crop->price_per_mt,
        'partial_loss_factor' => (string) $crop->partial_loss_factor,
        ...($changes[$crop->name] ?? []),
    ]])->all();
}

it('shows the reference values to administrators', function () {
    $this->actingAs($this->admin)->get('/damage-reports')
        ->assertSee('Crises &amp; Crop Values', false)
        ->assertSee('Loss = (Total + factor × Partial) × Yield · Cost = Loss × Price')
        ->assertSee('name="crops['.Crop::where('name', 'Rice')->value('id').'][price_per_mt]"', false);
});

it('updates crop values and audits only the changes', function () {
    $this->actingAs($this->admin)->put(route('damage.crops.update'), ['crops' => cropValues(['Rice' => ['price_per_mt' => '22000']])])
        ->assertRedirect(route('damage.index'))->assertSessionHas('status', 'Crop reference values saved.');

    $log = AuditLog::where('action', 'Updated Crop Reference Values')->sole();
    expect(Crop::where('name', 'Rice')->value('price_per_mt'))->toEqual('22000.00')
        ->and($log->old_values)->toBe(['Rice' => ['price_per_mt' => '20000.00']])
        ->and($log->new_values)->toBe(['Rice' => ['price_per_mt' => '22000.00']]);
});

it('adds a crop with its values', function () {
    $this->actingAs($this->admin)->put(route('damage.crops.update'), [
        'crops' => cropValues(),
        'new_crop' => ['name' => '  Ginger ', 'yield_mt_per_ha' => '12', 'price_per_mt' => '40000', 'partial_loss_factor' => '0.4'],
    ])->assertSessionHasNoErrors();

    expect(Crop::where('name', 'Ginger')->sole())->yield_mt_per_ha->toEqual('12.00')->partial_loss_factor->toEqual('0.40')
        ->and(AuditLog::where('action', 'Updated Crop Reference Values')->sole()->new_values)->toHaveKey('Ginger');
});

it('applies new values to new reports only', function () {
    $filed = DamageReport::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Juan'))->sole();
    $this->actingAs($this->admin)->put(route('damage.crops.update'), ['crops' => cropValues(['Rice' => ['price_per_mt' => '30000']])]);

    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
    $this->post('/damage-reports', [
        'disaster_id' => $filed->disaster_id, 'beneficiary_id' => $ana->id, 'barangay_id' => $ana->barangay_id,
        'crop_id' => $filed->crop_id, 'crop_stage' => 'vegetative', 'total_area_ha' => '1', 'partial_area_ha' => '0',
        'photos' => [UploadedFile::fake()->image('field.jpg')],
    ])->assertSessionHasNoErrors();

    expect($filed->fresh()->cost)->toEqual('108000.00')
        ->and(DamageReport::where('beneficiary_id', $ana->id)->sole()->cost)->toEqual('120000.00');
});

it('adds a disaster that the form then offers', function () {
    $this->actingAs($this->admin)->post(route('damage.disasters.store'), ['name' => 'Typhoon Egay', 'occurred_on' => '2026-08-01'])
        ->assertRedirect(route('damage.index'))->assertSessionHas('status', 'Typhoon Egay added.');

    expect(AuditLog::where('action', 'Added Crisis')->sole()->record_label)->toBe('Typhoon Egay (Aug 1, 2026)');
    $this->get('/damage-reports/create')->assertSee('Typhoon Egay');
    $this->get('/damage-reports')->assertSee('Crisis: Typhoon Egay');
});

it('rejects invalid values', function (string $route, array $input, string $field) {
    $method = $route === 'damage.crops.update' ? 'put' : 'post';

    $this->actingAs($this->admin)->{$method}(route($route), $input)->assertSessionHasErrorsIn('reference', [$field]);
})->with([
    'factor above 1' => ['damage.crops.update', fn () => ['crops' => cropValues(['Rice' => ['partial_loss_factor' => '1.5']])], 'crops.*'],
    'negative price' => ['damage.crops.update', fn () => ['crops' => cropValues(['Corn' => ['price_per_mt' => '-1']])], 'crops.*'],
    'zero yield' => ['damage.crops.update', fn () => ['crops' => cropValues(['Corn' => ['yield_mt_per_ha' => '0']])], 'crops.*'],
    'duplicate crop' => ['damage.crops.update', fn () => ['crops' => cropValues(), 'new_crop' => ['name' => 'rice', 'yield_mt_per_ha' => '1', 'price_per_mt' => '1', 'partial_loss_factor' => '0.5']], 'new_crop.name'],
    'crop missing values' => ['damage.crops.update', fn () => ['crops' => cropValues(), 'new_crop' => ['name' => 'Ginger']], 'new_crop.yield_mt_per_ha'],
    'duplicate disaster' => ['damage.disasters.store', ['name' => 'typhoon cristina', 'occurred_on' => '2026-07-20'], 'name'],
    'future disaster' => ['damage.disasters.store', fn () => ['name' => 'Typhoon Future', 'occurred_on' => now()->addDay()->toDateString()], 'occurred_on'],
]);

it('reports invalid crop values with a readable message', function () {
    $this->actingAs($this->admin)->put(route('damage.crops.update'), ['crops' => cropValues(['Rice' => ['partial_loss_factor' => '1.5']])])
        ->assertSessionHasErrorsIn('reference', ['crops.*' => 'Rice: the partial-damage factor must be between 0 and 1.']);

    expect(Crop::where('name', 'Rice')->value('partial_loss_factor'))->toEqual('0.50');
});

it('is for administrators only', function (string $username) {
    $user = User::where('username', $username)->sole();

    $this->actingAs($user)->put(route('damage.crops.update'), ['crops' => cropValues()])->assertForbidden();
    $this->post(route('damage.disasters.store'), ['name' => 'X', 'occurred_on' => '2026-08-01'])->assertForbidden();
    $this->get('/damage-reports')->assertDontSee('Crises &amp; Crop Values', false);
})->with(['Agritech_02', 'Encoder_03']);

it('seeds crises common in Bontoc and the highlands, Typhoon Cristina still the latest', function () {
    expect(Disaster::orderBy('name')->pluck('name')->all())->toBe([
        'El Niño Drought', 'Fall Armyworm Infestation', 'Frost (Cold Spell)', 'Rain-Induced Landslide',
        'Rice Black Bug Infestation', 'Southwest Monsoon Flooding', 'Typhoon Cristina',
    ])
        ->and(Disaster::orderByDesc('occurred_on')->value('name'))->toBe('Typhoon Cristina')
        ->and(Disaster::where('occurred_on', '>', today())->exists())->toBeFalse();

    $this->actingAs($this->admin)->get('/damage-reports')->assertSee('placeholder="e.g. Rain-Induced Landslide"', false);
});
