<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBeneficiaryRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BeneficiaryController extends Controller
{
    public function index(Request $request): View
    {
        $name = mb_substr($request->queryText('name'), 0, 100);
        $rsbsa = mb_substr($request->queryText('rsbsa'), 0, 50);
        $barangay = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;

        $beneficiaries = Beneficiary::forTable()
            ->when($name !== '', fn ($q) => $q->search($name))
            ->when($rsbsa !== '', fn ($q) => $q->whereRaw("LOWER(rsbsa_number) LIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($rsbsa)).'%',
            ]))
            ->when($barangay, fn ($q) => $q->where('barangay_id', $barangay))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate(15)->withQueryString();

        return view('beneficiaries.index', [
            'beneficiaries' => $beneficiaries,
            'barangays' => Barangay::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Request $request, Beneficiary $beneficiary): View
    {
        $user = $request->user();

        return view('beneficiaries.show', [
            'beneficiary' => $beneficiary->load(['barangay:id,name', 'household']),
            'others' => $beneficiary->otherHouseholdMembers(),
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
        $beneficiary->fill($request->validated());
        $beneficiary->rsbsa_number = $request->validated('rsbsa_number') ?: null;
        $beneficiary->updated_by = $request->user()->id;

        // Entering the masterlist number completes the RSBSA registration.
        if ($beneficiary->rsbsa_number && $beneficiary->rsbsa_status !== Beneficiary::RSBSA_REGISTERED) {
            $beneficiary->rsbsa_status = Beneficiary::RSBSA_REGISTERED;
            $beneficiary->rsbsa_status_reason = null;
        }
        if ($beneficiary->rsbsa_number && $beneficiary->encoding_issue === 'Missing RSBSA Number') {
            $beneficiary->encoding_issue = null;
        }

        $beneficiary->save();

        return redirect()->route('beneficiaries.show', $beneficiary)->with('status', 'Profile saved.');
    }
}
