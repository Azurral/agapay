<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\InterventionRecord;
use Illuminate\View\View;

/** Agri Tech "Beneficiary Validation": every active record still waiting for an eligibility check. */
class ValidationQueueController extends Controller
{
    public function __invoke(): View
    {
        return view('validation.index', [
            'records' => InterventionRecord::with(['beneficiary.barangay:id,name', 'intervention', 'cycle'])
                ->where('validation_status', InterventionRecord::VALIDATION_PENDING)
                ->whereIn('beneficiary_id', Beneficiary::select('id'))
                ->orderByDesc('distribution_cycle_id')->orderBy('id')
                ->paginate(15),
        ]);
    }
}
