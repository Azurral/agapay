<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Household;

/** Groups beneficiaries who share an address in the same barangay into one household. */
final class HouseholdService
{
    /** Words that only restate the barangay and must not split households. */
    private const BARANGAY_WORDS = ['barangay', 'brgy', 'bgy'];

    public static function normalizeAddress(string $address, string $barangayName): string
    {
        $text = mb_strtolower($address);
        $text = str_replace(['.', ','], ' ', $text);

        $remove = [...self::BARANGAY_WORDS, mb_strtolower($barangayName)];
        foreach ($remove as $word) {
            $text = preg_replace('/\b'.preg_quote($word, '/').'\b/u', ' ', $text);
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /** Finds or creates the household for the beneficiary's current address (sets household_id; does not save). */
    public static function assign(Beneficiary $beneficiary): Household
    {
        $barangay = Barangay::findOrFail($beneficiary->barangay_id);

        $household = Household::firstOrCreate([
            'barangay_id' => $barangay->id,
            'address_key' => self::normalizeAddress((string) $beneficiary->address, $barangay->name),
        ]);

        if (! $household->household_no) {
            $household->forceFill(['household_no' => sprintf('HH-%05d', $household->id)])->save();
        }

        $beneficiary->household_id = $household->id;

        return $household;
    }
}
