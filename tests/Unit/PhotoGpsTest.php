<?php

use App\Support\PhotoGps;
use Tests\Support\GpsJpeg;

function tempJpeg(?float $lat, ?float $lng): string
{
    $path = tempnam(sys_get_temp_dir(), 'gps').'.jpg';
    file_put_contents($path, GpsJpeg::bytes($lat, $lng));

    return $path;
}

it('reads the GPS position a phone camera stores in a photo', function () {
    expect(PhotoGps::read(tempJpeg(17.089412, 120.977031)))
        ->toEqualWithDelta(['latitude' => 17.089412, 'longitude' => 120.977031], 0.00001);
});

it('reads southern and western positions as negative', function () {
    expect(PhotoGps::read(tempJpeg(-14.5, -58.25)))->toEqualWithDelta(['latitude' => -14.5, 'longitude' => -58.25], 0.00001);
});

it('finds no position in a photo without GPS, a non-image, or a missing file', function () {
    $text = tempnam(sys_get_temp_dir(), 'txt');
    file_put_contents($text, 'not a photo');

    expect(PhotoGps::read(tempJpeg(null, null)))->toBeNull()
        ->and(PhotoGps::read($text))->toBeNull()
        ->and(PhotoGps::read('/no/such/file.jpg'))->toBeNull();
});

it('measures the distance between two points in metres', function () {
    // Bontoc town hall to Samoki, about 1 km apart.
    expect(PhotoGps::distance(17.0894, 120.9770, 17.0894, 120.9770))->toBe(0.0)
        ->and(PhotoGps::distance(17.0894, 120.9770, 17.0984, 120.9770))->toEqualWithDelta(1000.8, 2.0);
});

it('reads when the photo was taken (the camera DateTimeOriginal), with or without GPS', function () {
    $both = tempnam(sys_get_temp_dir(), 'gps').'.jpg';
    file_put_contents($both, GpsJpeg::bytes(17.0894, 120.977, takenAt: '2026-11-18 07:45:10'));
    $timeOnly = tempnam(sys_get_temp_dir(), 'gps').'.jpg';
    file_put_contents($timeOnly, GpsJpeg::bytes(null, null, takenAt: '2026-10-02 16:05:00'));

    expect(PhotoGps::takenAt($both)?->format('Y-m-d H:i:s'))->toBe('2026-11-18 07:45:10')
        ->and(PhotoGps::read($both))->toEqualWithDelta(['latitude' => 17.0894, 'longitude' => 120.977], 0.00001)
        ->and(PhotoGps::takenAt($timeOnly)?->format('Y-m-d H:i:s'))->toBe('2026-10-02 16:05:00')
        ->and(PhotoGps::read($timeOnly))->toBeNull()
        ->and(PhotoGps::takenAt(tempJpeg(17.0894, 120.977)))->toBeNull()
        ->and(PhotoGps::takenAt('/no/such/file.jpg'))->toBeNull();
});
