<?php

namespace App\Support;

use App\Models\AssistanceRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Collection;

/**
 * The details windows behind the home page's numbers and bars (like the Crisis Reports cards): click a tile or a bar
 * and see what it is made of. Only the parts for the widgets the user shows are worked out, and everything is counted
 * by the database, so Home stays fast with thousands of farmers.
 */
final class DashboardDetails
{
    /** Pending requests listed in the window; the rest are on the LGU Requests tab. */
    public const REQUESTS = 50;

    /** Latest stock movements shown per item. */
    public const MOVEMENTS = 5;

    /**
     * @param  list<string>  $widgets
     * @return array{barangays?: list<array<string, mixed>>, crops?: list<array{name: string, value: int}>, verification?: array<string, int>,
     *               programs?: list<array<string, mixed>>, requests?: array{rows: Collection<int, AssistanceRequest>, total: int},
     *               stock?: list<array<string, mixed>>}
     */
    public static function build(array $widgets): array
    {
        $has = fn (string ...$ids) => array_intersect($ids, $widgets) !== [];
        $cycle = DistributionCycle::current();
        $details = [];

        if ($has('tile_farmers', 'chart_barangays')) {
            $details['barangays'] = self::barangays($cycle);
            $details['crops'] = self::crops();
            $details['verification'] = Beneficiary::query()->selectRaw('verification_status AS s, COUNT(*) AS n')->groupBy('verification_status')
                ->pluck('n', 's')->map(fn ($n) => (int) $n)->all();
        }
        if ($has('tile_released', 'chart_releases')) {
            $details['programs'] = $cycle ? self::programs($cycle) : [];
        }
        if ($has('tile_requests')) {
            $pending = AssistanceRequest::where('status', AssistanceRequest::PENDING);
            $details['requests'] = [
                'rows' => (clone $pending)->with(['beneficiary.barangay:id,name', 'intervention:id,source,name', 'crop:id,name', 'disaster:id,name'])
                    ->oldest()->oldest('id')->limit(self::REQUESTS)->get(),
                'total' => (clone $pending)->count(),
            ];
        }
        if ($has('tile_low_stock', 'chart_stock')) {
            $details['stock'] = self::stock();
        }

        return $details;
    }

    /** Every barangay: farmers, RSBSA-registered, their crops, and this cycle's program records. */
    private static function barangays(?DistributionCycle $cycle): array
    {
        $farmers = Beneficiary::query()->toBase()->whereNull('deleted_at')
            ->selectRaw('barangay_id, COUNT(*) AS farmers, SUM(CASE WHEN rsbsa_status = ? THEN 1 ELSE 0 END) AS rsbsa', [Beneficiary::RSBSA_REGISTERED])
            ->groupBy('barangay_id')->get()->keyBy('barangay_id');
        $crops = Beneficiary::query()->toBase()->whereNull('deleted_at')->whereNotNull('crop_type')
            ->selectRaw('barangay_id, crop_type, COUNT(*) AS n')->groupBy('barangay_id', 'crop_type')->get()->groupBy('barangay_id');
        $records = $cycle
            ? InterventionRecord::query()->toBase()->where('distribution_cycle_id', $cycle->id)->whereNull('intervention_records.deleted_at')
                ->join('beneficiaries', 'beneficiaries.id', '=', 'intervention_records.beneficiary_id')->whereNull('beneficiaries.deleted_at')
                ->selectRaw("beneficiaries.barangay_id, COUNT(*) AS total, SUM(CASE WHEN claim_status = 'claimed' THEN 1 ELSE 0 END) AS claimed")
                ->groupBy('beneficiaries.barangay_id')->get()->keyBy('barangay_id')
            : collect();

        return collect(Barangay::options())
            ->map(fn (string $name, $id) => [
                'id' => (int) $id,
                'name' => $name,
                'farmers' => (int) ($farmers[$id]->farmers ?? 0),
                'rsbsa' => (int) ($farmers[$id]->rsbsa ?? 0),
                'records' => (int) ($records[$id]->total ?? 0),
                'claimed' => (int) ($records[$id]->claimed ?? 0),
                'crops' => collect($crops[$id] ?? [])->sortByDesc('n')->map(fn ($c) => ['name' => $c->crop_type, 'value' => (int) $c->n])->values()->all(),
            ])
            ->sortBy([['farmers', 'desc'], ['name', 'asc']])->values()->all();
    }

    /** @return list<array{name: string, value: int}> */
    private static function crops(): array
    {
        return Beneficiary::query()->whereNotNull('crop_type')->selectRaw('crop_type, COUNT(*) AS n')->groupBy('crop_type')
            ->orderByDesc('n')->orderBy('crop_type')->get()->map(fn ($c) => ['name' => $c->crop_type, 'value' => (int) $c->n])->all();
    }

    /** Each program of the cycle, split by barangay: claimed, waiting, not claimable. */
    private static function programs(DistributionCycle $cycle): array
    {
        $claimable = implode(',', array_map(fn (string $s) => "'".$s."'", InterventionRecord::CLAIMABLE));
        $rows = InterventionRecord::query()->toBase()->where('distribution_cycle_id', $cycle->id)->whereNull('intervention_records.deleted_at')
            ->join('beneficiaries', 'beneficiaries.id', '=', 'intervention_records.beneficiary_id')->whereNull('beneficiaries.deleted_at')
            ->selectRaw('intervention_id, beneficiaries.barangay_id, COUNT(*) AS total,'
                ." SUM(CASE WHEN claim_status = 'claimed' THEN 1 ELSE 0 END) AS claimed,"
                ." SUM(CASE WHEN claim_status <> 'claimed' AND forward_status IS NULL AND validation_status IN ({$claimable}) THEN 1 ELSE 0 END) AS waiting")
            ->groupBy('intervention_id', 'beneficiaries.barangay_id')->get();
        $programs = Intervention::whereIn('id', $rows->pluck('intervention_id')->unique())->get(['id', 'source', 'name', 'kind'])->keyBy('id');
        $barangays = Barangay::options();

        return $rows->groupBy('intervention_id')->map(function (Collection $group, $id) use ($programs, $barangays) {
            $split = $group->map(fn ($r) => [
                'name' => $barangays[$r->barangay_id] ?? 'Unknown',
                'claimed' => (int) $r->claimed,
                'waiting' => (int) $r->waiting,
                'not_claimable' => (int) $r->total - (int) $r->claimed - (int) $r->waiting,
                'total' => (int) $r->total,
            ])->sortBy([['total', 'desc'], ['name', 'asc']])->values();
            $program = $programs[$id] ?? null;

            return [
                'id' => (int) $id,
                'source' => $program?->source,
                'name' => strtoupper((string) $program?->source).' - '.$program?->name,
                'kind' => Intervention::KINDS[$program?->kind] ?? 'Goods',
                'claimed' => $split->sum('claimed'),
                'waiting' => $split->sum('waiting'),
                'not_claimable' => $split->sum('not_claimable'),
                'total' => $split->sum('total'),
                'barangays' => $split->all(),
            ];
        })->sortBy([['total', 'desc'], ['name', 'asc']])->values()->all();
    }

    /** Every stock item: received, released, balance, its programs and its latest movements. */
    private static function stock(): array
    {
        $items = InventoryItem::withStock()->with('interventions:id,inventory_item_id,source,name')->orderBy('name')->get();
        // A few rows per item (each release adds a movement, so an item can have thousands).
        $movements = $items->mapWithKeys(fn (InventoryItem $item) => [$item->id => InventoryMovement::where('inventory_item_id', $item->id)
            ->orderByDesc('movement_date')->orderByDesc('id')->limit(self::MOVEMENTS)->get(['id', 'direction', 'quantity', 'movement_date', 'notes'])]);

        return $items->map(fn (InventoryItem $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'unit' => $item->pluralUnit($item->balance()),
            'in' => $item->stockIn(),
            'out' => $item->stockOut(),
            'balance' => $item->balance(),
            'threshold' => (float) $item->low_stock_threshold,
            'low' => $item->isLow(),
            'programs' => $item->interventions->map(fn ($p) => strtoupper($p->source).' - '.$p->name)->all(),
            'movements' => $movements[$item->id]->map(fn (InventoryMovement $m) => [
                'date' => $m->movement_date?->format('M j, Y'),
                'in' => $m->direction === InventoryMovement::IN,
                'quantity' => (float) $m->quantity,
                'notes' => $m->notes,
            ])->values()->all(),
        ])->sortByDesc('low')->values()->all();
    }
}
