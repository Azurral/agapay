<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBeneficiaryRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BeneficiaryController extends Controller
{
    public function index(Request $request): View
    {
        $name = mb_substr($request->queryText('name'), 0, 100);
        $rsbsa = mb_substr($request->queryText('rsbsa'), 0, 50);
        $barangay = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;
        $interventionId = ctype_digit($request->queryText('intervention')) ? (int) $request->queryText('intervention') : null;

        $beneficiaries = Beneficiary::forTable()
            ->when($name !== '', fn ($q) => $q->search($name))
            ->when($rsbsa !== '', fn ($q) => $q->whereRaw("LOWER(rsbsa_number) LIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($rsbsa)).'%',
            ]))
            ->when($barangay, fn ($q) => $q->where('barangay_id', $barangay))
            ->when($interventionId, fn ($q) => $q->whereHas('interventionRecords', fn ($r) => $r->where('intervention_id', $interventionId)))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate(15)->withQueryString();

        return view('beneficiaries.index', [
            'beneficiaries' => $beneficiaries,
            'barangays' => Barangay::orderBy('name')->pluck('name', 'id'),
            'interventionOptions' => InterventionRecordController::interventionOptions(),
        ]);
    }

    public function show(Request $request, Beneficiary $beneficiary): View
    {
        $user = $request->user();

        $records = $beneficiary->interventionRecords()->with(['intervention', 'cycle'])
            ->orderByDesc('distribution_cycle_id')->orderByDesc('id')->get();
        $others = $beneficiary->otherHouseholdMembers();
        $cycle = DistributionCycle::current();
        $claimable = $records->filter(fn (InterventionRecord $r) => ! $r->isClaimed()
            && in_array($r->validation_status, InterventionRecord::CLAIMABLE, true))->values();
        // The banner's claim check covers every cycle Process Claim offers, the current one first.
        $cycleIds = $claimable->pluck('distribution_cycle_id')->push($cycle?->id)->filter()->unique()->values();

        return view('beneficiaries.show', [
            'beneficiary' => $beneficiary->load(['barangay:id,name', 'household']),
            'others' => $others,
            'records' => $records,
            'claimable' => $claimable,
            'currentCycleId' => $cycle?->id,
            'householdClaim' => $cycleIds->isNotEmpty() && $others->isNotEmpty()
                ? InterventionRecord::with(['beneficiary', 'intervention', 'cycle'])
                    ->whereIn('beneficiary_id', $others->pluck('id'))
                    ->whereIn('distribution_cycle_id', $cycleIds)
                    ->where('claim_status', InterventionRecord::CLAIM_CLAIMED)
                    ->orderByRaw('CASE WHEN distribution_cycle_id = ? THEN 0 ELSE 1 END', [$cycle?->id ?? 0])
                    ->latest('date_distributed')->latest('id')->first()
                : null,
            // 430:1461 is the only Data Encoder profile frame, so encoders always edit;
            // Agri Techs verify eligibility (407:1181); Administrators process claims (329:2822).
            'variant' => match (true) {
                $user->role?->slug === Role::ENCODER && $user->can('beneficiaries.manage') => 'edit',
                $user->role?->slug === Role::AGRITECH => 'eligibility',
                default => 'claim',
            },
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        $old = $beneficiary->only(['rsbsa_number']);
        $beneficiary->fill($request->validated());
        $beneficiary->rsbsa_number = $request->validated('rsbsa_number') ?: null;
        $beneficiary->updated_by = $request->user()->id;

        // A first number gets its own audit entry, so the trail shows when the farmer joined the RSBSA.
        $recordsNumber = ! $old['rsbsa_number'] && $beneficiary->rsbsa_number;

        try {
            DB::transaction(function () use ($beneficiary, $recordsNumber, $old) {
                $beneficiary->save();

                if ($recordsNumber) {
                    AuditLogger::record('Recorded RSBSA Number', $beneficiary, null, $old, $beneficiary->only(array_keys($old)));
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            if (! Beneficiary::isRsbsaClash($e)) {
                throw $e;
            }

            // Someone saved the same number after this form passed validation.
            return back()->withInput()->withErrors(['rsbsa_number' => "RSBSA No. {$beneficiary->rsbsa_number} is already used by another profile."]);
        }

        return redirect()->route('beneficiaries.show', $beneficiary)->with('status', 'Profile saved.');
    }
}
