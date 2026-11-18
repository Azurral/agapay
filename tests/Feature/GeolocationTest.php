<?php

use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GpsJpeg;

// Panel: "All farmers' lands must have geolocation. The system reads the geolocation of the uploaded photos for
// reporting. Wrong geolocation = system rejection."

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    config(['agapay.geo.check_photos' => true]);
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->ana = Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Dela Cruz'])->sole();
    $this->ana->forceFill(['farm_latitude' => 17.0894, 'farm_longitude' => 120.9770])->saveQuietly();
});

function gpsPhoto(?float $lat, ?float $lng, string $name = 'field.jpg'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'gps').'.jpg';
    file_put_contents($path, GpsJpeg::bytes($lat, $lng));

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

function geoDamageInput(Beneficiary $farmer, array $photos): array
{
    return [
        'disaster_id' => Disaster::where('name', 'Southwest Monsoon Flooding')->value('id'),
        'beneficiary_id' => $farmer->id,
        'barangay_id' => $farmer->barangay_id,
        'crop_id' => Crop::where('name', 'Rice')->value('id'),
        'crop_stage' => 'reproductive',
        'total_area_ha' => '1',
        'partial_area_ha' => '0',
        'photos' => $photos,
    ];
}

it('requires the farm\'s GPS location when a farmer is added', function () {
    $this->actingAs($this->encoder)
        ->post('/beneficiaries', validRegistration(['farm_latitude' => '', 'farm_longitude' => '']))
        ->assertSessionHasErrors(['farm_latitude' => "Enter the farm's GPS location (latitude and longitude)."]);

    $this->post('/beneficiaries', validRegistration(['farm_latitude' => '95', 'farm_longitude' => '120.97']))
        ->assertSessionHasErrors('farm_latitude');
});

it('saves the farm location and shows it on a map link', function () {
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['farm_latitude' => '17.091', 'farm_longitude' => '120.98']));
    $farmer = Beneficiary::latest('id')->firstOrFail();

    expect((float) $farmer->farm_latitude)->toBe(17.091)->and((float) $farmer->farm_longitude)->toBe(120.98);
    $this->get(route('beneficiaries.show', $farmer))->assertSee('openstreetmap.org/?mlat=17.091', false);
});

it('accepts a photo taken at the farm and keeps where it was taken', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', geoDamageInput($this->ana, [gpsPhoto(17.0899, 120.9772)]))
        ->assertSessionHasNoErrors();

    $photo = DamagePhoto::sole();
    $report = DamageReport::latest('id')->firstOrFail();
    expect((float) $photo->latitude)->toEqualWithDelta(17.0899, 0.00001)
        ->and($photo->distance_m)->toBeLessThan(100)
        ->and((float) $report->latitude)->toEqualWithDelta(17.0899, 0.00001);   // the report's position comes from the photo

    $this->get(route('damage.show', $report))->assertSee('m from the farm');
});

it('rejects a photo taken too far from the farm', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', geoDamageInput($this->ana, [gpsPhoto(16.4023, 120.5960, 'baguio.jpg')]))
        ->assertSessionHasErrors(['photos' => "baguio.jpg was taken 86.5 km from Ana Dela Cruz's farm (at most 2 km). Attach photos taken at the farm."]);

    expect(DamageReport::where('beneficiary_id', $this->ana->id)->where('created_at', '>', now()->subMinute())->exists())->toBeFalse();
});

it('rejects a photo without a GPS location', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', geoDamageInput($this->ana, [gpsPhoto(null, null, 'no-gps.jpg')]))
        ->assertSessionHasErrors(['photos' => "no-gps.jpg has no GPS location. Take the photo with the phone's location turned on."]);
});

it('does not keep a rejected photo for the next try', function () {
    $this->actingAs($this->encoder)->post('/damage-reports', geoDamageInput($this->ana, [gpsPhoto(16.4023, 120.5960, 'baguio.jpg')]));

    expect(session('damage_staged_photos', []))->toBe([]);
});

it('asks for the farm location before a damage report can be filed', function () {
    $this->ana->forceFill(['farm_latitude' => null, 'farm_longitude' => null])->saveQuietly();

    $this->actingAs($this->encoder)->post('/damage-reports', geoDamageInput($this->ana, [gpsPhoto(17.0899, 120.9772)]))
        ->assertSessionHasErrors(['beneficiary_id' => "Add the farm's GPS location to Ana Dela Cruz's profile first; the photos are checked against it."]);
});

it('gives every sample farmer a farm location', function () {
    expect(Beneficiary::whereNull('farm_latitude')->count())->toBe(0);
});
