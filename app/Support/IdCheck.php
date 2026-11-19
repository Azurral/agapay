<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Automatic checks on an ID photo at registration (panel: a verified farmer must have proof the encoder cannot fake).
 * What a program can check is checked here; whether the ID itself and its signature are genuine is left to the
 * Administrator, who sees these results beside the photo before approving.
 *
 * Each check is ['check' => what was checked, 'result' => pass | fail | unknown].
 */
final class IdCheck
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const UNKNOWN = 'unknown';

    public function __construct(private readonly IdTextReader $reader) {}

    /** @return list<array{check: string, result: string}> */
    public function check(string $path, string $idLabel, string $firstName, string $lastName, string $number): array
    {
        $size = @getimagesize($path);
        $checks = [[
            'check' => "{$idLabel}: a clear photo (at least 400 px wide)",
            'result' => $size !== false && max($size[0], $size[1]) >= 400 ? self::PASS : self::FAIL,
        ]];

        $text = $this->reader->read($path);
        if ($text === null) {
            $why = $this->reader->available() ? 'text could not be read' : 'text reader (OCR) not installed';

            return [...$checks,
                ['check' => "{$idLabel}: name on the ID ({$why})", 'result' => self::UNKNOWN],
                ['check' => "{$idLabel}: number on the ID ({$why})", 'result' => self::UNKNOWN],
            ];
        }

        return [...$checks,
            ['check' => "{$idLabel}: name {$firstName} {$lastName} printed on the ID", 'result' => self::nameFound($text, $firstName, $lastName) ? self::PASS : self::FAIL],
            ['check' => "{$idLabel}: number {$number} printed on the ID", 'result' => self::numberFound($text, $number) ? self::PASS : self::FAIL],
        ];
    }

    /**
     * Every word of the last name and the first word of the first name appear in the text; OCR often misreads one
     * letter, so a word of 4+ letters may differ by one.
     */
    public static function nameFound(string $text, string $firstName, string $lastName): bool
    {
        $words = self::words($text);
        $needed = [...self::words($lastName), ...array_slice(self::words($firstName), 0, 1)];
        if ($words === [] || $needed === []) {
            return false;
        }

        foreach ($needed as $name) {
            $found = false;
            foreach ($words as $word) {
                if ($word === $name || (strlen($name) >= 4 && abs(strlen($word) - strlen($name)) <= 1 && levenshtein($word, $name) <= 1)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /** The ID number, without dashes or spaces, appears in the text (letters OCR mistakes for digits counted as them). */
    public static function numberFound(string $text, string $number): bool
    {
        $wanted = self::digitsish($number);

        return strlen($wanted) >= 4 && str_contains(self::digitsish($text), self::digitsOnly($wanted));
    }

    /** @return list<string> upper-case ASCII words */
    private static function words(string $text): array
    {
        $ascii = strtoupper(Str::ascii($text));

        return array_values(array_filter(preg_split('/[^A-Z]+/', $ascii) ?: [], fn ($w) => strlen($w) >= 2));
    }

    /** Letters and digits only, upper case, with O/I/L/S/B read as 0/1/1/5/8 where they stand among digits. */
    private static function digitsish(string $text): string
    {
        $plain = preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($text))) ?? '';

        return preg_replace_callback('/(?<=\d)[OILSB]|[OILSB](?=\d)/', fn ($m) => strtr($m[0], 'OILSB', '01158'), $plain) ?? $plain;
    }

    /** The digits of a number like "VIN-1234-5678" (1234 5678), the part an OCR reading must contain. */
    private static function digitsOnly(string $plain): string
    {
        return preg_replace('/\D/', '', $plain) ?? '';
    }
}
