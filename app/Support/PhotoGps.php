<?php

namespace App\Support;

/**
 * Reads the GPS position a phone camera stores in a photo (EXIF GPSLatitude / GPSLongitude), without the PHP exif
 * extension (the office copy's PHP has none). JPEG: the APP1 "Exif" segment; PNG: the eXIf chunk. Both hold a TIFF
 * structure whose IFD0 points to the GPS IFD.
 */
final class PhotoGps
{
    /** @return array{latitude: float, longitude: float}|null null when the photo carries no usable GPS position */
    public static function read(string $path): ?array
    {
        $data = @file_get_contents($path, false, null, 0, 512 * 1024);   // the EXIF block sits at the start
        if ($data === false || strlen($data) < 12) {
            return null;
        }

        $tiff = str_starts_with($data, "\xFF\xD8") ? self::jpegTiff($data) : (str_starts_with($data, "\x89PNG") ? self::pngTiff($data) : null);

        return $tiff === null ? null : self::gps($tiff);
    }

    /** Distance in metres between two points (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    private static function jpegTiff(string $data): ?string
    {
        $offset = 2;
        $length = strlen($data);
        while ($offset + 4 <= $length && $data[$offset] === "\xFF") {
            $marker = ord($data[$offset + 1]);
            $size = unpack('n', substr($data, $offset + 2, 2))[1];
            if ($marker === 0xE1 && substr($data, $offset + 4, 6) === "Exif\0\0") {
                return substr($data, $offset + 10, $size - 8);
            }
            if ($marker === 0xDA || $size < 2) {   // image data starts: no EXIF before it
                return null;
            }
            $offset += 2 + $size;
        }

        return null;
    }

    private static function pngTiff(string $data): ?string
    {
        $offset = 8;
        while ($offset + 8 <= strlen($data)) {
            $size = unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);
            if ($type === 'eXIf') {
                return substr($data, $offset + 8, $size);
            }
            if ($type === 'IDAT' || $type === 'IEND') {
                return null;
            }
            $offset += 12 + $size;
        }

        return null;
    }

    /** @return array{latitude: float, longitude: float}|null */
    private static function gps(string $tiff): ?array
    {
        if (strlen($tiff) < 8) {
            return null;
        }
        $little = substr($tiff, 0, 2) === 'II';
        if (! $little && substr($tiff, 0, 2) !== 'MM') {
            return null;
        }
        $u16 = fn (int $at) => $at + 2 <= strlen($tiff) ? unpack($little ? 'v' : 'n', substr($tiff, $at, 2))[1] : null;
        $u32 = fn (int $at) => $at + 4 <= strlen($tiff) ? unpack($little ? 'V' : 'N', substr($tiff, $at, 4))[1] : null;

        $gpsIfd = null;
        $ifd0 = $u32(4);
        $count = $ifd0 !== null ? $u16($ifd0) : null;
        for ($i = 0; $count !== null && $i < min($count, 200); $i++) {
            $entry = $ifd0 + 2 + $i * 12;
            if ($u16($entry) === 0x8825) {
                $gpsIfd = $u32($entry + 8);
                break;
            }
        }
        if ($gpsIfd === null || ($count = $u16($gpsIfd)) === null) {
            return null;
        }

        $tags = [];
        for ($i = 0; $i < min($count, 100); $i++) {
            $entry = $gpsIfd + 2 + $i * 12;
            $tag = $u16($entry);
            $type = $u16($entry + 2);
            if ($tag === 1 || $tag === 3) {            // GPSLatitudeRef / GPSLongitudeRef: "N"/"S", "E"/"W" (ASCII, inline)
                $tags[$tag] = substr($tiff, $entry + 8, 1);
            } elseif (($tag === 2 || $tag === 4) && $type === 5) {   // three RATIONALs: degrees, minutes, seconds
                $at = $u32($entry + 8);
                $parts = [];
                for ($j = 0; $j < 3; $j++) {
                    $num = $u32($at + $j * 8);
                    $den = $u32($at + $j * 8 + 4);
                    $parts[] = ($num === null || ! $den) ? 0.0 : $num / $den;
                }
                $tags[$tag] = $parts[0] + $parts[1] / 60 + $parts[2] / 3600;
            }
        }
        if (! isset($tags[2], $tags[4])) {
            return null;
        }

        $lat = ($tags[1] ?? 'N') === 'S' ? -$tags[2] : $tags[2];
        $lng = ($tags[3] ?? 'E') === 'W' ? -$tags[4] : $tags[4];
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) {
            return null;
        }

        return ['latitude' => round($lat, 6), 'longitude' => round($lng, 6)];
    }
}
