<?php

namespace App\Http\Controllers;

use App\Exceptions\InterventionRuleViolation;
use App\Models\Beneficiary;
use App\Models\InterventionRecord;
use App\Services\ClaimService;
use App\Services\EligibilityCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Eligibility Verification: records waiting for a person to confirm, each with the automatic checks. */
class VerificationController extends Controller
{
    public function __construct(private readonly EligibilityCheck $checks, private readonly ClaimService $claims) {}

    public function index(): View
    {
        $records = $this->waiting()->with(['beneficiary.barangay:id,name', 'intervention', 'cycle'])
            ->orderByDesc('distribution_cycle_id')->orderBy('id')->paginate(15);

        return view('verification.index', [
            'records' => $records,
            'checks' => $records->getCollection()->mapWithKeys(fn (InterventionRecord $r) => [$r->id => $this->checks->run($r)]),
            'passedCount' => $this->waiting()->get()->filter(fn (InterventionRecord $r) => $this->checks->passes($r))->count(),
        ]);
    }

    /** Confirms as Eligible every waiting record whose checks all pass; flagged ones stay for a person to decide. */
    public function confirmPassed(Request $request): RedirectResponse
    {
        $verified = 0;
        foreach ($this->waiting()->get() as $record) {
            if (! $this->checks->passes($record)) {
                continue;
            }
            try {
                $this->claims->validate($record, InterventionRecord::VALIDATION_ELIGIBLE, $request->user());
                $verified++;
            } catch (InterventionRuleViolation) {
                // Changed by someone else meanwhile; it stays in the list.
            }
        }

        return back()->with('status', $verified === 0
            ? 'No records passed every check; review the flagged ones below.'
            : "{$verified} ".Str::plural('record', $verified).' verified as Eligible.');
    }

    private function waiting()
    {
        return InterventionRecord::where('validation_status', InterventionRecord::VALIDATION_PENDING)
            ->whereIn('beneficiary_id', Beneficiary::select('id'));
    }
}
