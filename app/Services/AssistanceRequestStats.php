<?php

namespace App\Services;

use App\Models\AssistanceRequest;
use App\Models\DamageReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * How many farmers asked for help, grouped by barangay, sitio/purok, crisis and crop, with the hectares they reported
 * damaged. Small enough to compute in PHP (a few thousand requests at most).
 */
final class AssistanceRequestStats
{
    /** @param array{disaster?: int|null, status?: string|null, intervention?: int|null} $filters */
    public static function query(array $filters): Builder
    {
        return AssistanceRequest::query()
            ->whereHas('beneficiary', fn (Builder $b) => $b->whereNull('deleted_at'))
            ->when($filters['disaster'] ?? null, fn (Builder $q, $id) => $q->where('disaster_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['intervention'] ?? null, fn (Builder $q, $id) => $q->where('intervention_id', $id));
    }

    /**
     * @param  array{disaster?: int|null, status?: string|null, intervention?: int|null}  $filters
     * @return array{totals: array<string, int>, barangays: list<array>, sitios: list<array>, crises: list<array>, crops: list<array>}
     */
    public function build(array $filters): array
    {
        $requests = self::query($filters)
            ->with(['beneficiary.barangay:id,name', 'intervention:id,name', 'disaster:id,name', 'crop:id,name', 'record:id,claim_status'])
            ->get();
        $damage = DamageReport::whereIn('beneficiary_id', $requests->pluck('beneficiary_id')->unique())
            ->when($filters['disaster'] ?? null, fn (Builder $q, $id) => $q->where('disaster_id', $id))
            ->get(['beneficiary_id', 'disaster_id', 'total_area_ha', 'partial_area_ha']);

        return [
            'totals' => [
                'requests' => $requests->count(),
                'pending' => $requests->where('status', AssistanceRequest::PENDING)->count(),
                'approved' => $requests->where('status', AssistanceRequest::APPROVED)->count(),
                'denied' => $requests->where('status', AssistanceRequest::DENIED)->count(),
                'released' => $requests->filter->isReleased()->count(),
            ],
            'barangays' => $this->group($requests, fn ($r) => $r->beneficiary->barangay?->name ?? '—', $damage),
            'sitios' => $this->group($requests, fn ($r) => ($r->beneficiary->sitio ?: $r->beneficiary->address).' · '.$r->beneficiary->barangay?->name, $damage),
            'crises' => $this->group($requests, fn ($r) => $r->disaster?->name ?? 'No crisis given', $damage, byCrisis: true),
            'crops' => $this->group($requests, fn ($r) => $r->crop?->name ?? ($r->beneficiary->crop_type ?: 'Not given'), $damage),
        ];
    }

    /** @return list<array{name: string, requests: int, farmers: int, pending: int, approved: int, denied: int, damaged_ha: float, top: string}> */
    private function group(Collection $requests, callable $key, Collection $damage, bool $byCrisis = false): array
    {
        return $requests->groupBy($key)->map(function (Collection $group, string $name) use ($damage, $byCrisis) {
            $farmers = $group->pluck('beneficiary_id')->unique();
            $reports = $damage->whereIn('beneficiary_id', $farmers);
            if ($byCrisis) {
                $reports = $reports->whereIn('disaster_id', $group->pluck('disaster_id')->filter()->unique());
            }
            $top = $group->countBy(fn ($r) => $r->intervention->name)->sortDesc();

            return [
                'name' => $name,
                'requests' => $group->count(),
                'farmers' => $farmers->count(),
                'pending' => $group->where('status', AssistanceRequest::PENDING)->count(),
                'approved' => $group->where('status', AssistanceRequest::APPROVED)->count(),
                'denied' => $group->where('status', AssistanceRequest::DENIED)->count(),
                'damaged_ha' => round($reports->sum(fn ($r) => (float) $r->total_area_ha + (float) $r->partial_area_ha), 2),
                'top' => $top->keys()->first().' ('.$top->first().')',
            ];
        })->sortBy([['requests', 'desc'], ['name', 'asc']])->values()->all();
    }
}
