<?php

namespace App\Support;

use App\Models\AssistanceRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;

/**
 * The home page's data presentation: headline numbers and three charts (releases of the current cycle per program,
 * farmers per barangay, stock against its low-stock level). Archived records are left out, as on every list.
 */
final class DashboardOverview
{
    /** Barangays shown; the rest are added up in one "Others" bar. */
    public const BARANGAYS = 8;

    /**
     * @return array{
     *     tiles: array{farmers: int, cycle: string|null, claimed: int, assigned: int, pending_requests: int, low_stock: int},
     *     programs: list<array{name: string, claimed: int, waiting: int, not_claimable: int, total: int}>,
     *     barangays: list<array{name: string, value: int, percent: float}>,
     *     stock: list<array{name: string, balance: float, threshold: float, unit: string, low: bool, percent: float, mark: float}>
     * }
     */
    public static function build(): array
    {
        $cycle = DistributionCycle::current();
        $programs = $cycle ? self::programs($cycle) : [];
        $stock = self::stock();

        return [
            'tiles' => [
                'farmers' => Beneficiary::count(),
                'cycle' => $cycle?->code,
                'claimed' => array_sum(array_column($programs, 'claimed')),
                'assigned' => array_sum(array_column($programs, 'total')),
                'pending_requests' => AssistanceRequest::where('status', AssistanceRequest::PENDING)->count(),
                'low_stock' => count(array_filter($stock, fn (array $item) => $item['low'])),
            ],
            'programs' => $programs,
            'barangays' => self::barangays(),
            'stock' => $stock,
        ];
    }

    /** @return list<array{name: string, claimed: int, waiting: int, not_claimable: int, total: int}> */
    private static function programs(DistributionCycle $cycle): array
    {
        // Counted by the database, grouped per program (the scalability test showed that loading every record of
        // the cycle into PHP made Home slow with 5,000 farmers and 30 staff at once).
        $claimable = implode(',', array_map(fn (string $s) => "'".$s."'", InterventionRecord::CLAIMABLE));
        $counts = InterventionRecord::query()->toBase()
            ->where('distribution_cycle_id', $cycle->id)
            ->whereNull('deleted_at')
            ->whereIn('beneficiary_id', Beneficiary::select('id'))   // archived farmers stay out
            ->selectRaw('intervention_id, COUNT(*) AS total,'
                ." SUM(CASE WHEN claim_status = 'claimed' THEN 1 ELSE 0 END) AS claimed,"
                ." SUM(CASE WHEN claim_status <> 'claimed' AND forward_status IS NULL AND validation_status IN ({$claimable}) THEN 1 ELSE 0 END) AS waiting")
            ->groupBy('intervention_id')
            ->get();
        $names = Intervention::whereIn('id', $counts->pluck('intervention_id'))->get(['id', 'source', 'name'])->keyBy('id');

        return $counts->map(fn ($row) => [
            'name' => strtoupper((string) $names[$row->intervention_id]?->source).' - '.$names[$row->intervention_id]?->name,
            'claimed' => (int) $row->claimed,
            'waiting' => (int) $row->waiting,
            'not_claimable' => (int) $row->total - (int) $row->claimed - (int) $row->waiting,
            'total' => (int) $row->total,
        ])
            ->sortBy([['total', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /** @return list<array{name: string, value: int, percent: float}> */
    private static function barangays(): array
    {
        $counts = Beneficiary::query()->selectRaw('barangay_id, COUNT(*) AS farmers')->groupBy('barangay_id')
            ->pluck('farmers', 'barangay_id')->map(fn ($n) => (int) $n)->sortDesc();
        $names = Barangay::options();   // cached (id => name)

        $rows = $counts->map(fn (int $n, $id) => ['name' => $names[$id] ?? 'Unknown', 'value' => $n])
            ->sortBy([['value', 'desc'], ['name', 'asc']])->values();
        if ($rows->count() > self::BARANGAYS) {
            $others = $rows->slice(self::BARANGAYS - 1)->sum('value');
            $rows = $rows->take(self::BARANGAYS - 1)->push(['name' => 'Others', 'value' => $others]);
        }
        $max = max(1, (int) $rows->max('value'));

        return $rows->map(fn (array $row) => [...$row, 'percent' => round($row['value'] / $max * 100, 1)])->all();
    }

    /** @return list<array{name: string, balance: float, threshold: float, unit: string, low: bool, percent: float, mark: float}> */
    private static function stock(): array
    {
        $items = InventoryItem::withStock()->orderBy('name')->get();
        // One scale for all items: the largest of any balance or low-stock level fills the bar.
        $max = max(1.0, (float) $items->map(fn (InventoryItem $i) => max($i->balance(), (float) $i->low_stock_threshold))->max());

        return $items->map(fn (InventoryItem $item) => [
            'name' => $item->name,
            'balance' => $item->balance(),
            'threshold' => (float) $item->low_stock_threshold,
            'unit' => $item->pluralUnit($item->balance()),
            'low' => $item->isLow(),
            'percent' => round(max(0, $item->balance()) / $max * 100, 1),
            'mark' => round((float) $item->low_stock_threshold / $max * 100, 1),
        ])->all();
    }
}
