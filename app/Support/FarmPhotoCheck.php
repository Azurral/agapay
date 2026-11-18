<?php

namespace App\Support;

use App\Models\Beneficiary;
use Illuminate\Http\UploadedFile;

/**
 * Geolocation (panel: "wrong geolocation = system rejection"): a crisis damage photo must carry the GPS position where it
 * was taken, within agapay.geo.max_km of the farmer's farm.
 */
final class FarmPhotoCheck
{
    public static function enabled(): bool
    {
        return (bool) config('agapay.geo.check_photos', true);
    }

    /** @return array{latitude: float, longitude: float, distance_m: int|null}|null where the photo was taken */
    public static function measure(UploadedFile $photo, ?Beneficiary $farmer): ?array
    {
        $gps = PhotoGps::read($photo->getRealPath() ?: $photo->getPathname());
        if ($gps === null) {
            return null;
        }

        $distance = $farmer?->hasFarmLocation()
            ? (int) round(PhotoGps::distance((float) $farmer->farm_latitude, (float) $farmer->farm_longitude, $gps['latitude'], $gps['longitude']))
            : null;

        return [...$gps, 'distance_m' => $distance];
    }

    /** Why the photo is refused for this farmer, or null when it was taken at the farm. */
    public static function problem(UploadedFile $photo, Beneficiary $farmer): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        $name = $photo->getClientOriginalName() ?: 'A photo';
        $where = self::measure($photo, $farmer);
        if ($where === null) {
            return "{$name} has no GPS location. Take the photo with the phone's location turned on.";
        }

        $limit = (float) config('agapay.geo.max_km', 2);
        if ($where['distance_m'] !== null && $where['distance_m'] > $limit * 1000) {
            $km = number_format($where['distance_m'] / 1000, 1);
            $max = rtrim(rtrim(number_format($limit, 1), '0'), '.');

            return "{$name} was taken {$km} km from {$farmer->fullName()}'s farm (at most {$max} km). Attach photos taken at the farm.";
        }

        return null;
    }

    public static function missingFarmLocation(Beneficiary $farmer): ?string
    {
        return self::enabled() && ! $farmer->hasFarmLocation()
            ? "Add the farm's GPS location to {$farmer->fullName()}'s profile first; the photos are checked against it."
            : null;
    }
}
