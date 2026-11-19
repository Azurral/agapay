<?php

namespace Tests\Support;

/** Builds a JPEG with an EXIF GPS position, like a phone camera with location on (for tests and e2e fixtures). */
final class GpsJpeg
{
    /**
     * JPEG bytes; null coordinates give a photo without GPS, and $takenAt ("2026-11-19 08:30:00") adds the camera's
     * DateTimeOriginal, as a phone stores the moment the photo was taken.
     */
    public static function bytes(?float $lat, ?float $lng, int $width = 320, int $height = 200, ?string $takenAt = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 140, 60));
        imagestring($image, 5, 12, 12, 'AGAPAY test field photo', imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 3, 12, 40, (string) mt_rand(), imagecolorallocate($image, 255, 255, 255));   // each photo unique
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();

        $hasGps = $lat !== null && $lng !== null;
        if (! $hasGps && $takenAt === null) {
            return $jpeg;
        }

        return substr($jpeg, 0, 2).self::app1($hasGps ? $lat : null, $hasGps ? $lng : null, $takenAt).substr($jpeg, 2);   // right after SOI
    }

    private static function app1(?float $lat, ?float $lng, ?string $takenAt): string
    {
        // TIFF, little-endian: IFD0 (Exif IFD and/or GPS IFD pointers) at 8, then the GPS IFD with its rationals, then
        // the Exif IFD with the DateTimeOriginal text.
        $entries = ($takenAt !== null ? 1 : 0) + ($lat !== null ? 1 : 0);
        $next = 8 + 2 + 12 * $entries + 4;
        $gpsIfd = $lat !== null ? $next : null;
        if ($gpsIfd !== null) {
            $next = $gpsIfd + 2 + 4 * 12 + 4 + 48;
        }
        $exifIfd = $takenAt !== null ? $next : null;

        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', $entries);
        if ($exifIfd !== null) {
            $tiff .= pack('vvVV', 0x8769, 4, 1, $exifIfd);
        }
        if ($gpsIfd !== null) {
            $tiff .= pack('vvVV', 0x8825, 4, 1, $gpsIfd);
        }
        $tiff .= pack('V', 0);
        if ($gpsIfd !== null) {
            $rationals = $gpsIfd + 2 + 4 * 12 + 4;
            $tiff .= pack('v', 4)
                .pack('vvV', 1, 2, 2).($lat < 0 ? 'S' : 'N')."\0\0\0"
                .pack('vvVV', 2, 5, 3, $rationals)
                .pack('vvV', 3, 2, 2).($lng < 0 ? 'W' : 'E')."\0\0\0"
                .pack('vvVV', 4, 5, 3, $rationals + 24)
                .pack('V', 0)
                .self::dms(abs($lat)).self::dms(abs($lng));
        }
        if ($exifIfd !== null) {
            $tiff .= pack('v', 1).pack('vvVV', 0x9003, 2, 20, $exifIfd + 18).pack('V', 0)
                .str_replace('-', ':', substr($takenAt, 0, 10)).substr($takenAt, 10, 9)."\0";
        }

        $payload = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    /** Degrees, minutes, seconds as three RATIONALs (seconds to 1/10000). */
    private static function dms(float $value): string
    {
        $deg = (int) floor($value);
        $min = (int) floor(($value - $deg) * 60);
        $sec = (int) round((($value - $deg) * 60 - $min) * 60 * 10000);

        return pack('VVVVVV', $deg, 1, $min, 1, $sec, 10000);
    }
}
