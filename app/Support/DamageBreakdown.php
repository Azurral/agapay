<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use Illuminate\Database\Eloquent\Builder;

/**
 * The data behind each Crisis Reports summary card (panel: clicking a card shows what makes up the number). Built from
 * the same filtered query as the cards, the charts, the Excel export and the PDF, so the totals always agree.
 */
final class DamageBreakdown
{
    /** Farmers listed in the Farmers Affected window; the page's own list pages through all of them. */
    public const FARMERS = 200;

    /**
     * @return array{
     *     farmers: list<array{id: int, name: string, barangay: string, crop: string, area: float, cost: float, status: string, report: int}>,
     *     area_by_barangay: list<array{name: string, total: float, partial: float, value: float, percent: float}>,
     *     area_by_crop: list<array{name: string, total: float, partial: float, value: float, percent: float}>,
     *     loss_by_crop: list<array{name: string, value: float, farmers: int, yield: float, percent: float}>,
     *     cost_by_barangay: list<array{name: string, value: float, farmers: int, percent: float}>,
     *     cost_by_crop: list<array{name: string, value: float, farmers: int, percent: float}>,
     *     cost_by_crisis: list<array{name: string, value: float, farmers: int, percent: float}>
     * }
     */
    public static function build(Builder $filtered): array
    {
        $barangays = Barangay::options()->all();
        $crops = Crop::pluck('name', 'id')->all();

        return [
            'farmers' => (clone $filtered)->with(['beneficiary', 'barangay:id,name', 'crop:id,name'])
                ->orderByDesc('cost')->limit(self::FARMERS)->get()
                ->map(fn (DamageReport $r) => [
                    'id' => $r->beneficiary_id,
                    'name' => $r->beneficiary?->fullName() ?? 'Unknown',
                    'barangay' => $r->barangay?->name ?? '',
                    'crop' => $r->crop?->name ?? '',
                    'area' => (float) $r->total_area_ha + (float) $r->partial_area_ha,
                    'cost' => (float) $r->cost,
                    'status' => $r->status === DamageReport::VALIDATED ? 'Validated' : 'For Validation',
                    'report' => $r->id,
                ])->all(),
            'area_by_barangay' => self::area($filtered, 'barangay_id', $barangays),
            'area_by_crop' => self::area($filtered, 'crop_id', $crops),
            'loss_by_crop' => self::percent((clone $filtered)->toBase()
                ->selectRaw('crop_id AS g, COALESCE(SUM(loss_mt), 0) AS value, COUNT(DISTINCT beneficiary_id) AS farmers, MAX(yield_mt_per_ha) AS yield')
                ->groupBy('crop_id')->get()
                ->map(fn ($r) => ['name' => $crops[$r->g] ?? 'Unknown', 'value' => (float) $r->value, 'farmers' => (int) $r->farmers, 'yield' => (float) $r->yield])
                ->sortByDesc('value')->values()->all()),
            'cost_by_barangay' => self::cost($filtered, 'barangay_id', $barangays),
            'cost_by_crop' => self::cost($filtered, 'crop_id', $crops),
            'cost_by_crisis' => self::cost($filtered, 'disaster_id', Disaster::pluck('name', 'id')->all()),
        ];
    }

    /** @param array<int, string> $names */
    private static function area(Builder $filtered, string $column, array $names): array
    {
        return self::percent((clone $filtered)->toBase()
            ->selectRaw("{$column} AS g, COALESCE(SUM(total_area_ha), 0) AS total, COALESCE(SUM(partial_area_ha), 0) AS partial")
            ->groupBy($column)->get()
            ->map(fn ($r) => ['name' => $names[$r->g] ?? 'Unknown', 'total' => (float) $r->total, 'partial' => (float) $r->partial, 'value' => (float) $r->total + (float) $r->partial])
            ->sortByDesc('value')->values()->all());
    }

    /** @param array<int, string> $names */
    private static function cost(Builder $filtered, string $column, array $names): array
    {
        return self::percent((clone $filtered)->toBase()
            ->selectRaw("{$column} AS g, COALESCE(SUM(cost), 0) AS value, COUNT(DISTINCT beneficiary_id) AS farmers")
            ->groupBy($column)->get()
            ->map(fn ($r) => ['name' => $names[$r->g] ?? 'Unknown', 'value' => (float) $r->value, 'farmers' => (int) $r->farmers])
            ->sortByDesc('value')->values()->all());
    }

    /** Adds each row's bar length, the largest full width. */
    private static function percent(array $rows): array
    {
        $max = max(0.01, ...array_map(fn ($r) => $r['value'], $rows ?: [['value' => 0]]));

        return array_map(fn ($r) => [...$r, 'percent' => round($r['value'] / $max * 100, 1)], $rows);
    }
}
