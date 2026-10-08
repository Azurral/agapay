<?php

use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use App\Support\StagedPhotos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
});

function keptPhotoInput(array $overrides = []): array
{
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();

    return [
        'disaster_id' => Disaster::where('name', 'Southwest Monsoon Flooding')->value('id'),
        'beneficiary_id' => $ana->id, 'barangay_id' => $ana->barangay_id,
        'crop_id' => Crop::where('name', 'Rice')->value('id'), 'crop_stage' => 'vegetative',
        'total_area_ha' => '1', 'partial_area_ha' => '0',
        ...$overrides,
    ];
}

it('keeps the attached photos when the form comes back with an error', function () {
    $this->actingAs($this->encoder)->from('/damage-reports/create')
        ->post('/damage-reports', keptPhotoInput(['beneficiary_id' => '', 'photos' => [UploadedFile::fake()->image('field.jpg')]]))
        ->assertRedirect('/damage-reports/create');

    $staged = StagedPhotos::all();
    expect($staged)->toHaveCount(1)
        ->and(Storage::disk('local')->exists(array_values($staged)[0]['path']))->toBeTrue();

    $token = array_key_first($staged);
    $this->get('/damage-reports/create')
        ->assertSee('name="kept_photos[]" value="'.$token.'"', false)
        ->assertSee(route('damage.staged-photos.show', $token), false)
        ->assertSee('Your photos are kept')
        ->assertSee('Choose a farmer from the list.')
        ->assertDontSee('Choose the photos again');
    $this->get(route('damage.staged-photos.show', $token))->assertOk();
});

it('files the report with the kept photos on the next try', function () {
    $this->actingAs($this->encoder)->from('/damage-reports/create')
        ->post('/damage-reports', keptPhotoInput(['beneficiary_id' => '', 'photos' => [UploadedFile::fake()->image('field.jpg')]]));
    $token = array_key_first(StagedPhotos::all());
    $stagedPath = StagedPhotos::all()[$token]['path'];

    $this->post('/damage-reports', keptPhotoInput(['kept_photos' => [$token], 'photos' => [UploadedFile::fake()->image('second.png')]]))
        ->assertSessionHasNoErrors();

    $report = DamageReport::where('beneficiary_id', $this->ana->id)->sole();
    expect($report->photos()->pluck('original_name')->sort()->values()->all())->toBe(['field.jpg', 'second.png'])
        ->and(Storage::disk('local')->exists($report->photos()->first()->path))->toBeTrue()
        ->and(Storage::disk('local')->exists($stagedPath))->toBeFalse()
        ->and(StagedPhotos::all())->toBe([]);
});

it('keeps the photos when the report itself is refused after validation', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', keptPhotoInput(['photos' => [UploadedFile::fake()->image('a.jpg')]]));

    $this->from('/damage-reports/create')
        ->post('/damage-reports', keptPhotoInput(['photos' => [UploadedFile::fake()->image('b.jpg')]]))
        ->assertSessionHasErrors('beneficiary_id');   // already has a Rice report for this crisis

    expect(collect(StagedPhotos::all())->pluck('name')->all())->toBe(['b.jpg']);
});

it('ignores kept photos that are not this session\'s', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', keptPhotoInput(['kept_photos' => ['made-up-token']]))
        ->assertSessionHasErrors(['photos' => 'Attach at least one photo of the damage (JPG or PNG).']);

    $this->get(route('damage.staged-photos.show', 'made-up-token'))->assertNotFound();
});

it('counts kept and new photos together against the limit of ten', function () {
    $this->actingAs($this->encoder)->from('/damage-reports/create')->post('/damage-reports', keptPhotoInput([
        'beneficiary_id' => '', 'photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6)),
    ]));
    $tokens = array_keys(StagedPhotos::all());

    $this->post('/damage-reports', keptPhotoInput([
        'kept_photos' => $tokens, 'photos' => array_map(fn ($i) => UploadedFile::fake()->image("q{$i}.jpg"), range(1, 5)),
    ]))->assertSessionHasErrors(['photos' => 'Attach up to 10 photos.']);

    expect(DamageReport::where('beneficiary_id', $this->ana->id)->exists())->toBeFalse()
        ->and(StagedPhotos::all())->toHaveCount(10);
});

it('drops kept photos when the form is opened fresh', function () {
    $this->actingAs($this->encoder)->from('/damage-reports/create')
        ->post('/damage-reports', keptPhotoInput(['beneficiary_id' => '', 'photos' => [UploadedFile::fake()->image('field.jpg')]]));
    $path = array_values(StagedPhotos::all())[0]['path'];

    $this->get('/damage-reports/create');   // the error page
    $this->get('/damage-reports/create')->assertDontSee('kept_photos');   // a later visit

    expect(StagedPhotos::all())->toBe([])->and(Storage::disk('local')->exists($path))->toBeFalse();
});
