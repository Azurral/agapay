<?php

namespace App\Services;

use App\Exceptions\ReportFileException;
use App\Exports\DistributionReportExport;
use App\Models\Beneficiary;
use App\Models\GeneratedReport;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Support\MemoryLimit;
use App\Support\ReportCriteria;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Distribution monitoring report for one cycle (spec rule 12; paper §1.4.1): per-intervention and per-barangay
 * assigned / claimed / unclaimed, the beneficiary list and the inventory used.
 */
final class ReportService
{
    /** Validation statuses that end a record's claim (spec rule 6). */
    public const NOT_CLAIMABLE = [InterventionRecord::VALIDATION_DUPLICATE, InterventionRecord::VALIDATION_RELOCATED, InterventionRecord::VALIDATION_INACTIVE];

    /**
     * @return array{
     *     criteria: array<string, string>,
     *     summary: array{beneficiaries: int, assigned: int, claimed: int, unclaimed: int, not_claimable: int},
     *     interventions: list<array{source: string, name: string, unit: string, assigned: int, claimed: int, unclaimed: int, not_claimable: int, quantity: float}>,
     *     barangays: list<array{name: string, beneficiaries: int, assigned: int, claimed: int, unclaimed: int, not_claimable: int, quantity: float}>,
     *     beneficiaries: list<array{name: string, rsbsa: string, barangay: string, program: string, quantity: string, validation: string, claim: string, date: string}>,
     *     inventory: list<array{item: string, unit_label: string, used: float}>
     * }
     */
    public function build(ReportCriteria $criteria): array
    {
        $records = $this->records($criteria)
            ->with(['beneficiary.barangay:id,name', 'intervention'])
            ->get()
            ->sortBy(fn (InterventionRecord $r) => [
                (string) $r->beneficiary->barangay?->name, mb_strtolower($r->beneficiary->last_name),
                mb_strtolower($r->beneficiary->first_name), $r->intervention->source, $r->intervention->name,
            ])
            ->values();

        return [
            'criteria' => $criteria->labels(),
            'summary' => [
                'beneficiaries' => $records->pluck('beneficiary_id')->unique()->count(),
                ...$this->counts($records),
            ],
            'interventions' => $records->groupBy('intervention_id')
                ->map(fn (Collection $group) => [
                    'source' => $group->first()->intervention->sourceLabel(),
                    'name' => $group->first()->intervention->name,
                    'unit' => (string) $group->first()->intervention->unit,
                    ...$this->counts($group),
                    'quantity' => $this->quantity($group),
                ])
                ->sortBy(fn (array $row) => [$row['source'], $row['name']])->values()->all(),
            // Counts only: a barangay mixes sacks, liters and cash, so a quantity sum would mean nothing.
            'barangays' => $records->groupBy(fn (InterventionRecord $r) => (string) $r->beneficiary->barangay?->name)
                ->map(fn (Collection $group, string $name) => [
                    'name' => $name,
                    'beneficiaries' => $group->pluck('beneficiary_id')->unique()->count(),
                    ...$this->counts($group),
                ])
                ->sortKeys()->values()->all(),
            'beneficiaries' => $records->map(fn (InterventionRecord $r) => [
                'name' => $r->beneficiary->fullName(),
                'rsbsa' => $r->beneficiary->rsbsaDisplay(),
                'barangay' => (string) $r->beneficiary->barangay?->name,
                'program' => $r->intervention->sourcedName(),
                'quantity' => $r->quantityDisplay(),
                'validation' => InterventionRecord::validationLabel($r->validation_status),
                'claim' => ! $r->isClaimed() && in_array($r->validation_status, self::NOT_CLAIMABLE, true) ? 'Not Claimable' : $r->claimLabel(),
                'date' => $r->date_distributed?->format('M j, Y') ?? '—',
            ])->all(),
            'inventory' => $this->inventory($criteria),
        ];
    }

    /** Renders the report as PDF or .xlsx, keeps the file and records it in the history (spec §4) and the audit trail. */
    public function generate(ReportCriteria $criteria, User $actor): GeneratedReport
    {
        set_time_limit(300);
        MemoryLimit::atLeast('512M');   // DomPDF needs ~0.4 MB per table row

        $data = $this->build($criteria);
        $fileName = sprintf('agapay-report-%s-%s-%s.%s', Str::slug($criteria->cycle->code), $criteria->program, today()->format('Y-m-d'), $criteria->format);
        $contents = $criteria->format === 'xlsx'
            ? Excel::raw(new DistributionReportExport($data), ExcelWriter::XLSX)
            : Pdf::loadView('reports.pdf', [
                ...$data,
                'generatedBy' => $actor->name,
                'generatedAt' => now(),
                'maxRows' => (int) config('agapay.report_pdf_max_rows', 1000),
            ])->setPaper('a4', 'landscape')->output();

        $path = 'reports/'.Str::uuid().'.'.$criteria->format;
        // The local disk does not throw on failure: a full disk or a permission problem must not leave a history row without a file.
        if (! Storage::disk(GeneratedReport::DISK)->put($path, $contents)) {
            throw new ReportFileException('The report file could not be saved. Check that the storage folder is writable, then try again.');
        }

        try {
            return DB::transaction(function () use ($criteria, $actor, $fileName, $path, $data) {
                $report = GeneratedReport::create([
                    'user_id' => $actor->id,
                    'distribution_cycle_id' => $criteria->cycle->id,
                    'program' => $criteria->program,
                    'start_date' => $criteria->start?->toDateString(),
                    'end_date' => $criteria->end?->toDateString(),
                    'format' => $criteria->format,
                    'file_name' => $fileName,
                    'path' => $path,
                    'rows' => count($data['beneficiaries']),
                ]);
                $labels = $criteria->labels();
                AuditLogger::record('Generated Distribution Report', $report, $fileName, [], [
                    'cycle' => $labels['cycle'], 'program' => $labels['program'], 'dates' => $labels['dates'],
                    'start' => $criteria->start?->toDateString(), 'end' => $criteria->end?->toDateString(),
                    'format' => $labels['format'], 'rows' => $report->rows,
                ], $actor);

                return $report;
            });
        } catch (Throwable $e) {
            Storage::disk(GeneratedReport::DISK)->delete($path);

            throw $e;
        }
    }

    /** @return Builder<InterventionRecord> non-archived records of the cycle, program and date range */
    public function records(ReportCriteria $criteria): Builder
    {
        // Records of archived farmers are left out, as in every working list (the record's relation includes
        // archived profiles, so filter on the profile table's own scope).
        return InterventionRecord::query()
            ->whereIn('beneficiary_id', Beneficiary::query()->select('id'))
            ->where('distribution_cycle_id', $criteria->cycle->id)
            ->when($criteria->program !== 'all', fn (Builder $q) => $q->ofSource($criteria->program))
            ->when($criteria->start || $criteria->end, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('claim_status', InterventionRecord::CLAIM_UNCLAIMED)
                ->orWhere(fn (Builder $q) => $q
                    ->where('claim_status', InterventionRecord::CLAIM_CLAIMED)
                    ->when($criteria->start, fn (Builder $q) => $q->whereDate('date_distributed', '>=', $criteria->start->toDateString()))
                    ->when($criteria->end, fn (Builder $q) => $q->whereDate('date_distributed', '<=', $criteria->end->toDateString())))));
    }

    /** @return array{assigned: int, claimed: int, unclaimed: int, not_claimable: int} */
    private function counts(Collection $records): array
    {
        $claimed = $records->filter(fn (InterventionRecord $r) => $r->isClaimed())->count();
        // Duplicate / relocated / inactive records will never be claimed: they are not "waiting".
        $notClaimable = $records->filter(fn (InterventionRecord $r) => ! $r->isClaimed()
            && in_array($r->validation_status, self::NOT_CLAIMABLE, true))->count();

        return [
            'assigned' => $records->count(), 'claimed' => $claimed,
            'unclaimed' => $records->count() - $claimed - $notClaimable, 'not_claimable' => $notClaimable,
        ];
    }

    private function quantity(Collection $records): float
    {
        return round($records->filter(fn (InterventionRecord $r) => $r->isClaimed())->sum(fn (InterventionRecord $r) => (float) $r->quantity), 2);
    }

    /**
     * Net automatic stock-out of the reported records, per item.
     *
     * @param  ReportCriteria  $criteria  the same records as the report (a subquery, not a long id list)
     * @return list<array{item: string, unit_label: string, used: float}>
     */
    private function inventory(ReportCriteria $criteria): array
    {
        $net = InventoryMovement::query()
            ->where('source', InventoryMovement::AUTO)
            ->whereIn('intervention_record_id', $this->records($criteria)->select('intervention_records.id'))
            ->get(['inventory_item_id', 'direction', 'quantity'])
            ->groupBy('inventory_item_id')
            ->map(fn (Collection $moves) => round($moves->sum(fn ($m) => $m->direction === InventoryMovement::OUT ? (float) $m->quantity : -(float) $m->quantity), 2))
            ->filter(fn (float $used) => $used != 0.0);

        return InventoryItem::whereKey($net->keys())->orderBy('name')->get()
            ->map(fn (InventoryItem $item) => ['item' => $item->name, 'unit_label' => $item->unit_label, 'used' => $net[$item->id]])
            ->all();
    }
}
