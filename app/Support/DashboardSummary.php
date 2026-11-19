<?php

namespace App\Support;

use App\Models\AssistanceRequest;
use App\Models\Beneficiary;
use App\Models\DamageReport;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Quarterly and yearly summary on Home (panel, Ma'am Libby): what OMAG did in each quarter (Jan–Mar, Apr–Jun,
 * Jul–Sep, Oct–Dec) of a year and in the whole year. Releases count by the day they were given out, requests by the day
 * they were filed or decided, and crisis reports by the day they were filed.
 */
final class DashboardSummary
{
    /**
     * @return array{year: int, years: list<int>, rows: list<array{key: string, label: string, money: bool, link: ?string, permission: ?string, values: list<int|float>}>}
     */
    public static function build(int $year): array
    {
        $from = CarbonImmutable::create($year)->startOfYear();
        $to = $from->endOfYear();
        $in = fn ($query, string $column) => $query->whereBetween($column, [$from, $to]);

        $released = $in(InterventionRecord::query()->toBase()->whereNull('intervention_records.deleted_at')
            ->where('claim_status', InterventionRecord::CLAIM_CLAIMED)
            ->join('interventions', 'interventions.id', '=', 'intervention_records.intervention_id'), 'date_distributed')
            ->get(['date_distributed', 'beneficiary_id', 'interventions.source', 'interventions.kind', 'quantity']);
        $requests = $in(AssistanceRequest::query()->toBase(), 'created_at')->get(['created_at']);
        $decided = $in(AssistanceRequest::query()->toBase()->whereIn('status', [AssistanceRequest::APPROVED, AssistanceRequest::DENIED]), 'decided_at')
            ->get(['decided_at', 'status']);
        $reports = $in(DamageReport::query()->toBase()->whereNull('deleted_at'), 'created_at')->get(['created_at', 'cost']);

        $rows = [
            self::row('registered', 'Farmers registered', self::count($in(Beneficiary::query()->toBase()->whereNull('deleted_at'), 'created_at')->pluck('created_at')), ['beneficiaries.index', [], 'beneficiaries.manage']),
            self::row('served', 'Farmers served (received a program)', self::distinct($released, 'date_distributed', 'beneficiary_id'), null),
            self::row('released_da', 'DA program releases', self::count($released->where('source', Intervention::SOURCE_DA)->pluck('date_distributed')), ['interventions.da', [], 'interventions.view']),
            self::row('released_lgu', 'LGU program releases', self::count($released->where('source', Intervention::SOURCE_LGU)->pluck('date_distributed')), ['interventions.lgu', [], 'interventions.view']),
            self::row('cash', 'Cash given out', self::sum($released->where('kind', Intervention::KIND_CASH), 'date_distributed', 'quantity'), null, true),
            self::row('requests', 'Assistance requests filed', self::count($requests->pluck('created_at')), ['interventions.lgu', ['tab' => 'requests'], 'interventions.view']),
            self::row('approved', 'Requests approved', self::count($decided->where('status', AssistanceRequest::APPROVED)->pluck('decided_at')), ['interventions.lgu', ['tab' => 'requests'], 'interventions.view']),
            self::row('crisis_reports', 'Crisis reports filed', self::count($reports->pluck('created_at')), ['damage.index', [], 'damage.view']),
            self::row('damage_cost', 'Estimated crisis damage', self::sum($reports, 'created_at', 'cost'), ['damage.index', [], 'damage.view'], true),
        ];

        return ['year' => $year, 'years' => self::years($year), 'rows' => $rows];
    }

    /** The years with releases, registrations or reports, and this year. */
    private static function years(int $chosen): array
    {
        $first = collect([
            InterventionRecord::min('date_distributed'), Beneficiary::min('created_at'), DamageReport::min('created_at'), AssistanceRequest::min('created_at'),
        ])->filter()->map(fn ($date) => (int) substr((string) $date, 0, 4))->min() ?? (int) now()->year;

        return range(max((int) now()->year, $chosen), min($first, $chosen));
    }

    /** @param  array{0: string, 1: array, 2: string}|null  $link  route name, parameters, the permission the page needs */
    private static function row(string $key, string $label, array $values, ?array $link, bool $money = false): array
    {
        return ['key' => $key, 'label' => $label, 'money' => $money, 'values' => $values,
            'link' => $link ? route($link[0], $link[1]) : null, 'permission' => $link[2] ?? null];
    }

    /** Quarter 0–3 of a stored date or date-time. */
    private static function quarter($date): int
    {
        return intdiv((int) substr((string) $date, 5, 2) - 1, 3);
    }

    /** @return list<int> Q1..Q4 and the year */
    private static function count(Collection $dates): array
    {
        $values = [0, 0, 0, 0];
        foreach ($dates as $date) {
            $values[self::quarter($date)]++;
        }

        return [...$values, array_sum($values)];
    }

    /** @return list<float> */
    private static function sum(Collection $rows, string $dateColumn, string $valueColumn): array
    {
        $values = [0.0, 0.0, 0.0, 0.0];
        foreach ($rows as $row) {
            $values[self::quarter($row->{$dateColumn})] += (float) $row->{$valueColumn};
        }

        return [...$values, array_sum($values)];
    }

    /** @return list<int> distinct people per quarter, and in the whole year */
    private static function distinct(Collection $rows, string $dateColumn, string $idColumn): array
    {
        $byQuarter = [[], [], [], []];
        foreach ($rows as $row) {
            $byQuarter[self::quarter($row->{$dateColumn})][$row->{$idColumn}] = true;
        }

        return [...array_map('count', $byQuarter), $rows->pluck($idColumn)->unique()->count()];
    }
}
