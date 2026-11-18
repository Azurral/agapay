<?php

namespace Tests\Support;

/** Builds a JPEG with an EXIF GPS position, like a phone camera with location on (for tests and e2e fixtures). */
final class GpsJpeg
{
    /** JPEG bytes; null coordinates give a photo without GPS. */
    public static function bytes(?float $lat, ?float $lng, int $width = 320, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 140, 60));
        imagestring($image, 5, 12, 12, 'AGAPAY test field photo', imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();

        if ($lat === null || $lng === null) {
            return $jpeg;
        }

        $app1 = self::app1($lat, $lng);

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);   // right after SOI
    }

    private static function app1(float $lat, float $lng): string
    {
        // TIFF, little-endian: IFD0 (1 entry: GPS IFD pointer) at 8, GPS IFD (4 entries) at 26, rationals after it.
        $gpsIfd = 8 + 2 + 12 + 4;
        $rationals = $gpsIfd + 2 + 4 * 12 + 4;
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 1).pack('vvVV', 0x8825, 4, 1, $gpsIfd).pack('V', 0)
            .pack('v', 4)
            .pack('vvV', 1, 2, 2).($lat < 0 ? 'S' : 'N')."\0\0\0"
            .pack('vvVV', 2, 5, 3, $rationals)
            .pack('vvV', 3, 2, 2).($lng < 0 ? 'W' : 'E')."\0\0\0"
            .pack('vvVV', 4, 5, 3, $rationals + 24)
            .pack('V', 0)
            .self::dms(abs($lat)).self::dms(abs($lng));

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
