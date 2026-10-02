<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRsbsaRegistrationRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RsbsaRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        return view('rsbsa.register', [
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
            'canRegister' => $request->user()->can('rsbsa.register'),
        ]);
    }

    public function store(StoreRsbsaRegistrationRequest $request): RedirectResponse
    {
        $beneficiary = Beneficiary::create([
            ...$request->validated(),
            'rsbsa_status' => Beneficiary::RSBSA_PENDING,
            'source' => Beneficiary::SOURCE_MANUAL,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('rsbsa.register')
            ->with('status', "{$beneficiary->fullName()} was registered and is awaiting OMAG validation.");
    }
}
