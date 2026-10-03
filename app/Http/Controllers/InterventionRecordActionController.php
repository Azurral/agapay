<?php

namespace App\Http\Controllers;

use App\Exceptions\InterventionRuleViolation;
use App\Models\InterventionRecord;
use App\Services\ClaimService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Validation, claim and unclaim of one intervention record (dropdowns, profile modals). */
class InterventionRecordActionController extends Controller
{
    public function __construct(private readonly ClaimService $claims) {}

    public function validate(Request $request, InterventionRecord $record): RedirectResponse
    {
        $status = $request->input('validation_status');

        return $this->attempt($record, fn () => $this->claims->validate($record, is_string($status) ? $status : '', $request->user()),
            fn (InterventionRecord $r) => InterventionRecord::validationLabel($r->validation_status));
    }

    public function claim(Request $request, InterventionRecord $record): RedirectResponse
    {
        $input = $request->only(['date_distributed', 'quantity', 'proxy_claimant', 'proof_note', 'override_reason']);
        // The encoder's "Distributed" dropdown records a past distribution (same as the Add/Edit form).
        $historical = $request->boolean('historical') && $request->user()->can('intervention_records.manage');

        return $this->attempt($record,
            fn () => $this->claims->claim($record, $request->user(), array_filter($input, fn ($v) => $v !== null && $v !== ''), $historical),
            fn (InterventionRecord $r) => $r->claimLabel());
    }

    public function unclaim(Request $request, InterventionRecord $record): RedirectResponse
    {
        return $this->attempt($record, fn () => $this->claims->unclaim($record, $request->user()), fn (InterventionRecord $r) => $r->claimLabel());
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
    private function attempt(InterventionRecord $record, Closure $action, Closure $stateLabel, ?string $label = null): RedirectResponse
    {
        try {
            $updated = $action();
        } catch (InterventionRuleViolation $e) {
            return back()->withErrors(['intervention' => $e->getMessage()], 'intervention')->with('intervention_failed', $record->id);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors(), 'intervention')->with('intervention_failed', $record->id);
        }

        return back()->with('status', ($label ?? $updated->auditRecordLabel()).": {$stateLabel($updated)}.");
    }
}
