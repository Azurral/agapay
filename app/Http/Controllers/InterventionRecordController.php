<?php

namespace App\Http\Controllers;

use App\Exceptions\InterventionRuleViolation;
use App\Http\Requests\InterventionRecordRequest;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Services\ClaimService;
use App\Services\InterventionAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Data Encoder "Intervention Records" (Figma 430:1603 list, 446:198 form). */
class InterventionRecordController extends Controller
{
    public function __construct(private readonly InterventionAssignment $assignment, private readonly ClaimService $claims) {}

    public function index(Request $request): View
    {
        $name = mb_substr($request->queryText('name'), 0, 100);
        $rsbsa = mb_substr($request->queryText('rsbsa'), 0, 50);
        $interventionId = ctype_digit($request->queryText('intervention')) ? (int) $request->queryText('intervention') : null;

        return view('intervention-records.index', [
            'records' => InterventionRecord::with(['beneficiary', 'intervention', 'cycle'])
                ->whereIn('beneficiary_id', Beneficiary::select('id'))
                ->when($name !== '', fn (Builder $q) => $q->whereHas('beneficiary', fn (Builder $b) => $b->search($name)))
                ->when($rsbsa !== '', fn (Builder $q) => $q->whereHas('beneficiary', fn (Builder $b) => $b->whereRaw(
                    "LOWER(rsbsa_number) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($rsbsa)).'%']
                )))
                ->when($interventionId, fn (Builder $q) => $q->where('intervention_id', $interventionId))
                ->orderByDesc('distribution_cycle_id')->orderBy('id')
                ->paginate(15)->withQueryString(),
            'interventionOptions' => self::interventionOptions(),
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function edit(InterventionRecord $record): View
    {
        return $this->form($record->load(['beneficiary.barangay', 'intervention', 'cycle']));
    }

    public function store(InterventionRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $stage = 'intervention_id';

        try {
            DB::transaction(function () use ($data, $request, &$stage) {
                $record = $this->assignment->assign(
                    Beneficiary::findOrFail($data['beneficiary_id']),
                    Intervention::findOrFail($data['intervention_id']),
                    DistributionCycle::findOrFail($data['distribution_cycle_id']),
                    ['quantity' => $data['quantity'] ?? null],
                    $request->user(),
                );

                if ($request->wantsDistributed()) {
                    $stage = 'distribution_status';
                    $this->claims->claim($record, $request->user(), ['date_distributed' => $data['date_distributed']], historical: true);
                }
            });
        } catch (InterventionRuleViolation $e) {
            return back()->withInput()->withErrors([$stage => $e->getMessage()]);
        }

        return redirect()->route('intervention-records.index')->with('status', 'Intervention record saved.');
    }

    public function update(InterventionRecordRequest $request, InterventionRecord $record): RedirectResponse
    {
        $data = $request->validated();
        $stage = 'distribution_status';

        try {
            DB::transaction(function () use ($data, $request, $record, &$stage) {
                // Unclaim before moving it; claim after, so the rules see the final intervention and cycle.
                if (! $request->wantsDistributed() && $record->isClaimed()) {
                    $record = $this->claims->unclaim($record, $request->user());
                }

                $stage = 'intervention_id';
                $record = $this->assignment->reassign($record, [
                    'quantity' => $data['quantity'] ?? null,
                    'intervention_id' => (int) $data['intervention_id'],
                    'distribution_cycle_id' => (int) $data['distribution_cycle_id'],
                    'date_distributed' => $record->isClaimed() ? $data['date_distributed'] : null,
                ], $request->user());

                $stage = 'distribution_status';
                if ($request->wantsDistributed() && ! $record->isClaimed()) {
                    $this->claims->claim($record, $request->user(), ['date_distributed' => $data['date_distributed']], historical: true);
                }
            });
        } catch (InterventionRuleViolation $e) {
            return back()->withInput()->withErrors([$stage => $e->getMessage()]);
        }

        return redirect()->route('intervention-records.index')->with('status', 'Intervention record saved.');
    }

    /** @return array<int, string> id => "DA - Certified Rice Seeds" */
    public static function interventionOptions(): array
    {
        return Intervention::orderBy('source')->orderBy('name')->get()
            ->mapWithKeys(fn (Intervention $i) => [$i->id => "{$i->sourceLabel()} - {$i->name}"])->all();
    }

    private function form(?InterventionRecord $record): View
    {
        return view('intervention-records.form', [
            'record' => $record,
            'interventions' => Intervention::active()->orderBy('name')->get(['id', 'source', 'name']),
            'cycles' => DistributionCycle::orderByDesc('code')->get(['id', 'code', 'label']),
            'currentCycleId' => DistributionCycle::current()?->id,
        ]);
    }
}
