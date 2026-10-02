<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Household;
use Illuminate\Support\Str;

/** Groups beneficiaries who share an address in the same barangay into one household. */
final class HouseholdService
{
    /** Words that only restate the barangay, municipality or province and must not split households. */
    private const PLACE_WORDS = ['barangay', 'brgy', 'bgy', 'bontoc', 'mountain province', 'mt province', 'mtn province', 'province'];

    /** Prefix of the address_key of a one-person household for an address too vague to group on. */
    private const SOLO_PREFIX = '~solo:';

    public static function normalizeAddress(string $address, string $barangayName): string
    {
        $text = mb_strtolower($address);
        $text = str_replace(['.', ','], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        // The barangay name goes first so "Bontoc Ili" is removed whole before "bontoc".
        foreach ([mb_strtolower($barangayName), ...self::PLACE_WORDS] as $words) {
            $text = preg_replace('/\b'.preg_quote($words, '/').'\b/u', ' ', $text);
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /** Finds or creates the household for the beneficiary's current address (sets household_id; does not save). */
    public static function assign(Beneficiary $beneficiary): Household
    {
        $barangay = Barangay::findOrFail($beneficiary->barangay_id);
        $key = self::normalizeAddress((string) $beneficiary->address, $barangay->name);

        if ($key === '') {
            // An address that only names the barangay says nothing about who lives together: never group it.
            $current = $beneficiary->household_id ? Household::find($beneficiary->household_id) : null;
            $household = $current && $current->barangay_id === $barangay->id && str_starts_with($current->address_key, self::SOLO_PREFIX)
                ? $current
                : Household::create(['barangay_id' => $barangay->id, 'address_key' => self::SOLO_PREFIX.Str::ulid()]);
        } else {
            $household = Household::firstOrCreate(['barangay_id' => $barangay->id, 'address_key' => $key]);
        }

        if (! $household->household_no) {
            $household->forceFill(['household_no' => sprintf('HH-%05d', $household->id)])->save();
        }

        $beneficiary->household_id = $household->id;

        return $household;
    }
}
