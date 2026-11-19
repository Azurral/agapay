<?php

namespace App\Http\Controllers;

use App\Exceptions\InterventionRuleViolation;
use App\Models\InterventionRecord;
use App\Models\ValidationProof;
use App\Services\ClaimService;
use App\Services\ValidationProofService;
use Closure;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PDOException;

/** Validation, claim and unclaim of one intervention record (dropdowns, profile modals). */
class InterventionRecordActionController extends Controller
{
    use DetectsConcurrencyErrors;

    public function __construct(private readonly ClaimService $claims, private readonly ValidationProofService $proofs) {}

    /** A change of status needs proof; an Agricultural Technologist's change then waits for the Administrator. */
    public function validate(Request $request, InterventionRecord $record): RedirectResponse
    {
        $status = $request->input('validation_status');
        $proof = null;

        return $this->attempt($record, function () use ($record, $status, $request, &$proof) {
            $result = $this->proofs->change($record, is_string($status) ? $status : '', $request->file('proof'), $request->user());
            $proof = $result['proof'];

            return $result['record'];
        }, function (InterventionRecord $r) use (&$proof) {
            return $proof?->status === ValidationProof::PENDING
                ? InterventionRecord::validationLabel($proof->to_status)." sent with proof for the Administrator's approval"
                : InterventionRecord::validationLabel($r->validation_status);
        });
    }

    public function claim(Request $request, InterventionRecord $record): RedirectResponse
    {
        $input = $request->only(['date_distributed', 'quantity', 'override_reason']);

        // Always a live claim: past distributions are encoded through the Add/Edit form, which requires their date.
        return $this->attempt($record,
            fn () => $this->claims->claim($record, $request->user(), array_filter($input, fn ($v) => $v !== null && $v !== '')),
            fn (InterventionRecord $r) => $r->claimLabel(), pop: true);
    }

    public function unclaim(Request $request, InterventionRecord $record): RedirectResponse
    {
        return $this->attempt($record, fn () => $this->claims->unclaim($record, $request->user()), fn (InterventionRecord $r) => $r->claimLabel(), pop: true);
    }

    public function archive(Request $request, InterventionRecord $record): RedirectResponse
    {
        $label = $record->auditRecordLabel();
        $reason = $request->input('reason');

        return $this->attempt($record, function () use ($record, $reason, $request) {
            $this->claims->archive($record, is_string($reason) ? $reason : null, $request->user());

            return $record;
        }, fn () => 'Archived', $label);
    }

    public function restore(Request $request, InterventionRecord $record): RedirectResponse
    {
        return $this->attempt($record, fn () => $this->claims->restore($record, $request->user()), fn () => 'Restored');
    }

    /**
     * Runs a service call; rule breaks and bad input return to the page in the "intervention" error bag.
     *
     * @param  Closure(): InterventionRecord  $action
     * @param  Closure(InterventionRecord): string  $stateLabel
     */
    private function attempt(InterventionRecord $record, Closure $action, Closure $stateLabel, ?string $label = null, bool $pop = false): RedirectResponse
    {
        try {
            $updated = $action();
        } catch (InterventionRuleViolation $e) {
            return back()->withInput()->withErrors(['intervention' => $e->getMessage()], 'intervention')->with('intervention_failed', $record->id);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors(), 'intervention')->with('intervention_failed', $record->id);
        } catch (PDOException $e) {   // QueryException, or DeadlockException from a nested transaction
            if (! $this->causedByConcurrencyError($e)) {
                throw $e;
            }

            return back()->withInput()->withErrors(['intervention' => 'Another claim for this household was being saved at the same moment. Please try again.'], 'intervention')
                ->with('intervention_failed', $record->id);
        }

        $redirect = back()->with('status', ($label ?? $updated->auditRecordLabel()).": {$stateLabel($updated)}.");

        // After a claim or unclaim, changed_record gives that row's claim chip a quick pop.
        return $pop ? $redirect->with('changed_record', $record->id) : $redirect;
    }
}
