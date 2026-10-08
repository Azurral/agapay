<?php

use App\Http\Requests\DamageReportRequest;
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
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

/** A valid New Damage Report submission for Ana Dela Cruz's rice after the monsoon floods. */
function damageInput(array $overrides = []): array
{
    $ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();

    return [
        'disaster_id' => Disaster::where('name', 'Southwest Monsoon Flooding')->value('id'),
        'beneficiary_id' => $ana->id,
        'barangay_id' => $ana->barangay_id,
        'crop_id' => Crop::where('name', 'Rice')->value('id'),
        'farm_location' => 'Sitio Lanao',
        'crop_stage' => 'reproductive',
        'total_area_ha' => '1.20',
        'partial_area_ha' => '0.30',
        'latitude' => '17.0894',
        'longitude' => '120.9750',
        'photos' => [UploadedFile::fake()->image('field.jpg')],
        ...$overrides,
    ];
}

it('shows the Figma form to every role', function (string $username) {
    $this->actingAs(User::where('username', $username)->sole())->get('/damage-reports/create')->assertOk()
        ->assertSee('NEW DAMAGE REPORT')
        ->assertSeeInOrder([
            'New Damage Report', 'Crisis:', 'Barangay:', 'Farmer / Beneficiary:', 'Crop / Farm Location:',
            'Crop Stage:', 'Total Area:', 'Partial Area:', 'GPS Coordinates', 'Latitude:', 'Longitude:',
            'Photo / Attachment', 'Drag &amp; drop photos here (JPG/PNG)', 'or click to browse', 'Choose Photo', 'Submit Damage Report',
        ], false);
})->with(['Admin_01', 'Agritech_02', 'Encoder_03']);

it('files a report with photos and computes loss', function () {
    $response = $this->actingAs($this->encoder)->post('/damage-reports', damageInput([
        'photos' => [UploadedFile::fake()->image('field-1.jpg'), UploadedFile::fake()->image('field-2.png')],
    ]));

    $report = DamageReport::latest('id')->firstOrFail();
    $response->assertRedirect(route('damage.show', $report))->assertSessionHas('status', 'Damage report filed for Ana Dela Cruz.');

    expect($report)->status->toBe(DamageReport::FOR_VALIDATION)->reported_by->toBe($this->encoder->id)
        ->loss_mt->toEqual('5.40')->cost->toEqual('108000.00')->yield_mt_per_ha->toEqual('4.00')
        ->latitude->toEqual('17.089400')->farm_location->toBe('Sitio Lanao')
        ->and($report->photos()->pluck('original_name')->all())->toBe(['field-1.jpg', 'field-2.png'])
        ->and(Storage::disk('local')->allFiles("damage/{$report->id}"))->toHaveCount(2)
        ->and(AuditLog::where('action', 'Added Damage Report')->sole()->record_label)->toBe('Ana Dela Cruz · Rice · Southwest Monsoon Flooding');
});

it('rejects bad input with field errors and stores nothing', function (array $overrides, string $field, string $message) {
    $before = DamageReport::count();

    $this->actingAs($this->encoder)->from('/damage-reports/create')->post('/damage-reports', damageInput($overrides))
        ->assertRedirect('/damage-reports/create')
        ->assertSessionHasErrors([$field => $message])
        ->assertSessionHasInput('farm_location', 'Sitio Lanao');

    // Only the photo kept for the next try (damage-staging) is on disk; nothing was filed.
    expect(DamageReport::count())->toBe($before)->and(Storage::disk('local')->allFiles('damage'))->toBe([]);
})->with([
    'no farmer' => [['beneficiary_id' => null], 'beneficiary_id', 'Choose a farmer from the list.'],
    'archived farmer' => [fn () => ['beneficiary_id' => tap(Beneficiary::factory()->create())->delete()->id], 'beneficiary_id', 'Choose a farmer from the list.'],
    'comma decimal' => [['total_area_ha' => '1,5'], 'total_area_ha', 'Enter the area in hectares, e.g. 1.5.'],
    'negative area' => [['partial_area_ha' => '-1'], 'partial_area_ha', 'Enter the area in hectares, e.g. 1.5.'],
    'no area' => [['total_area_ha' => '0', 'partial_area_ha' => '0'], 'total_area_ha', 'Enter the damaged area.'],
    'latitude only' => [['longitude' => null], 'longitude', 'Enter both latitude and longitude.'],
    'latitude out of range' => [['latitude' => '91'], 'latitude', 'Latitude must be between -90 and 90.'],
    'degree sign' => [['latitude' => '17.0894° N'], 'latitude', 'Enter the latitude in decimal degrees, e.g. 17.0894.'],
    'unknown stage' => [['crop_stage' => 'flowering'], 'crop_stage', 'Choose a crop stage.'],
    'big photo' => [fn () => ['photos' => [UploadedFile::fake()->image('big.jpg')->size(8000)]], 'photos.0', fn () => 'Each photo must be JPG or PNG and at most '.DamageReportRequest::photoLimitLabel().'.'],
    'pdf as jpg' => [fn () => ['photos' => [UploadedFile::fake()->create('scan.jpg', 100, 'application/pdf')]], 'photos.0', fn () => 'Each photo must be JPG or PNG and at most '.DamageReportRequest::photoLimitLabel().'.'],
    'eleven photos' => [fn () => ['photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 11))], 'photos', 'Attach up to 10 photos.'],
]);

it('refuses a second report for the same farmer, crop and disaster', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', damageInput());

    $this->post('/damage-reports', damageInput())
        ->assertSessionHasErrors(['beneficiary_id' => 'Ana Dela Cruz already has a Rice damage report for Southwest Monsoon Flooding.']);
    $this->post('/damage-reports', damageInput(['crop_id' => Crop::where('name', 'Corn')->value('id')]))->assertSessionHasNoErrors();

    expect(DamageReport::where('beneficiary_id', damageInput()['beneficiary_id'])->count())->toBe(2);
});

it('lets the reporter edit an unvalidated report and recalculates', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', damageInput(['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]));
    $report = DamageReport::latest('id')->firstOrFail();
    $removed = $report->photos()->first();

    $this->get(route('damage.edit', $report))->assertOk()->assertSee('EDIT DAMAGE REPORT')->assertSee('Save Changes');
    $this->put(route('damage.update', $report), damageInput([
        'total_area_ha' => '2', 'partial_area_ha' => '0', 'remove_photos' => [$removed->id], 'photos' => [UploadedFile::fake()->image('c.jpg')],
    ]))->assertRedirect(route('damage.show', $report));

    expect($report->fresh())->loss_mt->toEqual('8.00')->cost->toEqual('160000.00')
        ->and($report->photos()->pluck('original_name')->sort()->values()->all())->toBe(['b.jpg', 'c.jpg'])
        ->and(Storage::disk('local')->exists($removed->path))->toBeFalse();
});

it('lets only the reporter or a configurer edit', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', damageInput());
    $report = DamageReport::latest('id')->firstOrFail();

    $this->actingAs($this->agritech)->get(route('damage.edit', $report))->assertForbidden();
    $this->actingAs($this->admin)->get(route('damage.edit', $report))->assertOk();
});

it('keeps validated reports read-only', function () {
    $report = DamageReport::where('status', DamageReport::VALIDATED)->firstOrFail();

    $this->actingAs($this->admin)->get(route('damage.edit', $report))->assertForbidden();
    $this->put(route('damage.update', $report), damageInput())->assertForbidden();
});

it('serves photos only to signed-in users who can view damage reports', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', damageInput(['photos' => [UploadedFile::fake()->image('a.jpg')]]));
    $photo = DamageReport::latest('id')->firstOrFail()->photos()->sole();

    $this->get(route('damage.photos.show', $photo))->assertOk()->assertHeader('content-type', 'image/jpeg');
    auth()->logout();
    $this->get(route('damage.photos.show', $photo))->assertRedirect('/login');
});

it('lets damage reporters search farmers', function () {
    $this->actingAs($this->agritech)->getJson(route('beneficiaries.lookup', ['q' => 'Ana']))->assertOk()
        ->assertJsonFragment(['name' => 'Ana Dela Cruz', 'barangay' => 'Poblacion']);
});

it('turns a request too large for PHP into the photo message', function () {
    $this->actingAs($this->encoder)
        ->call('POST', '/damage-reports', server: ['CONTENT_LENGTH' => (string) (1024 * 1024 * 1024)])
        ->assertRedirect(route('damage.create', ['too_large' => 1]));

    $this->get(route('damage.create', ['too_large' => 1]))
        ->assertSee('The photos are too large to upload at once — up to 10 photos of 5 MB each.');
});

it('rounds areas to the stored two decimals before computing', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', damageInput(['total_area_ha' => '0.125', 'partial_area_ha' => '0']))->assertSessionHasNoErrors();

    expect(DamageReport::latest('id')->firstOrFail())->total_area_ha->toEqual('0.13')->loss_mt->toEqual('0.52')->cost->toEqual('10400.00');
});

it('tells the reporter the photos were kept after an error', function () {
    $this->actingAs($this->encoder)->from('/damage-reports/create')->followingRedirects()
        ->post('/damage-reports', damageInput(['total_area_ha' => '0', 'partial_area_ha' => '0', 'photos' => [UploadedFile::fake()->image('a.jpg')]]))
        ->assertSee('Your photos are kept')->assertDontSee('Choose the photos again');

    $this->get('/damage-reports/create')->assertDontSee('Your photos are kept');
});

it('tells the form the photo limits this server accepts', function () {
    $perFile = min(5 * 1024 * 1024, DamageReportRequest::bytes(ini_get('upload_max_filesize')));

    $this->actingAs($this->encoder)->get('/damage-reports/create')
        ->assertSee('data-max-photo-bytes="'.$perFile.'"', false)
        ->assertSee('data-max-post-bytes="'.DamageReportRequest::bytes(ini_get('post_max_size')).'"', false)
        ->assertSee('@change="add([...$event.target.files])"', false);
});

it('explains a photo PHP refused as too large', function () {
    $photo = new UploadedFile(UploadedFile::fake()->image('big.jpg')->getRealPath(), 'big.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
    $limit = DamageReportRequest::photoLimitLabel();

    $this->actingAs($this->encoder)->post('/damage-reports', damageInput(['photos' => [$photo]]))
        ->assertSessionHasErrors(['photos.0' => "Each photo must be JPG or PNG and at most {$limit}."]);
});

it('requires at least one photo on a new report', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', damageInput(['photos' => []]))
        ->assertSessionHasErrors(['photos' => 'Attach at least one photo of the damage (JPG or PNG).']);
});

it('keeps at least one photo when editing', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', damageInput());
    $report = DamageReport::latest('id')->firstOrFail();

    $this->put(route('damage.update', $report), damageInput(['photos' => [], 'remove_photos' => $report->photos()->pluck('id')->all()]))
        ->assertSessionHasErrors(['photos' => 'Keep or attach at least one photo of the damage.']);

    expect($report->photos()->count())->toBe(1);
});

it('says on the form that a photo is required', function () {
    $this->actingAs($this->encoder)->get('/damage-reports/create')->assertSee('At least 1 and up to 10 JPG or PNG photos');
});
