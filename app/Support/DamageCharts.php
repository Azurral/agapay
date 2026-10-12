<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\Crop;
use Illuminate\Database\Eloquent\Builder;

/**
 * The bar charts of the Crisis Reports page and the damage PDF (paper 1.4.1: "summarized reports and graphical
 * visualizations"): the cost of damage per barangay and per crop, for the same filtered reports as the summary cards.
 */
final class DamageCharts
{
    /** Bars per chart; any further groups are added up in one "Others" bar. */
    public const BARS = 8;

    /** @return array{barangays: list<array{name: string, value: float, farmers: int, percent: float}>, crops: list<array{name: string, value: float, farmers: int, percent: float}>} */
    public static function build(Builder $filtered): array
    {
        return [
            'barangays' => self::bars($filtered, 'barangay_id', Barangay::pluck('name', 'id')->all()),
            'crops' => self::bars($filtered, 'crop_id', Crop::pluck('name', 'id')->all()),
        ];
    }

    /** @param array<int, string> $names */
    private static function bars(Builder $filtered, string $column, array $names): array
    {
        // Grouped on the damage_reports table alone, so the page's filters never meet a joined column.
        $groups = (clone $filtered)->toBase()
            ->selectRaw("{$column} AS group_id, COALESCE(SUM(cost), 0) AS value, COUNT(DISTINCT beneficiary_id) AS farmers")
            ->groupBy($column)->get()
            ->map(fn ($g) => ['name' => $names[$g->group_id] ?? 'Unknown', 'value' => (float) $g->value, 'farmers' => (int) $g->farmers])
            ->sortByDesc('value')->values();

        if ($groups->count() > self::BARS) {
            $rest = $groups->slice(self::BARS - 1);
            $groups = $groups->take(self::BARS - 1)->push(['name' => 'Others', 'value' => $rest->sum('value'), 'farmers' => $rest->sum('farmers')]);
        }

        $max = max($groups->max('value') ?? 0, 0.01);

        return $groups->map(fn (array $g) => [...$g, 'percent' => round($g['value'] / $max * 100, 1)])->all();
    }
}
