<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Autocomplete for the Add Record and New Damage Report forms: existing (non-archived) beneficiaries only. */
class BeneficiaryLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = mb_substr($request->queryText('q'), 0, 100);

        if ($term === '') {
            return response()->json([]);
        }

        return response()->json(Beneficiary::with('barangay:id,name')->search($term)
            ->orderBy('last_name')->orderBy('first_name')->limit(10)->get()
            ->map(fn (Beneficiary $b) => [
                'id' => $b->id, 'name' => $b->fullName(), 'rsbsa' => $b->rsbsaDisplay(), 'barangay' => $b->barangay?->name,
                // Prefill the damage form with the farmer's own barangay, farm and crop.
                'barangay_id' => $b->barangay_id, 'address' => $b->address, 'crop_type' => $b->crop_type,
            ]));
    }
}
