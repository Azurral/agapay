<?php

namespace App\Services;

use App\Models\Crop;

/**
 * Spec rule 11: loss = (total + factor × partial) × yield, cost = loss × price.
 * Cost is worked out from the rounded loss so the figures shown always multiply out.
 */
final class DamageCalculator
{
    /** @return array{loss_mt: float, cost: float} */
    public static function calculate(float $totalHa, float $partialHa, float $factor, float $yieldMtPerHa, float $pricePerMt): array
    {
        $loss = round(($totalHa + $factor * $partialHa) * $yieldMtPerHa, 2);

        return ['loss_mt' => $loss, 'cost' => round($loss * $pricePerMt, 2)];
    }

    /** @return array{yield_mt_per_ha: float, price_per_mt: float, partial_loss_factor: float, loss_mt: float, cost: float} */
    public static function forCrop(Crop $crop, float $totalHa, float $partialHa): array
    {
        $snapshot = [
            'yield_mt_per_ha' => (float) $crop->yield_mt_per_ha,
            'price_per_mt' => (float) $crop->price_per_mt,
            'partial_loss_factor' => (float) $crop->partial_loss_factor,
        ];

        return [
            ...$snapshot,
            ...self::calculate($totalHa, $partialHa, $snapshot['partial_loss_factor'], $snapshot['yield_mt_per_ha'], $snapshot['price_per_mt']),
        ];
    }

    /** Summary card number: "842", "1.35", "54,000", "4.2M" (Figma 423:136–148). */
    public static function compact(float $value): string
    {
        if (abs($value) >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M';
        }

        return self::decimal($value);
    }

    /** Thousands separators, at most two decimals, no trailing zeros: "1,204", "5.4", "1.35". */
    public static function decimal(float $value): string
    {
        $text = number_format($value, 2);

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
