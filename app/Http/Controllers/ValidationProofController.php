<?php

namespace App\Http\Controllers;

use App\Exceptions\InterventionRuleViolation;
use App\Models\DamagePhoto;
use App\Models\InterventionRecord;
use App\Models\ValidationProof;
use App\Services\ValidationProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The Administrator's decision on a validation status change sent with proof (Walk-in Approvals page). */
class ValidationProofController extends Controller
{
    public function __construct(private readonly ValidationProofService $proofs) {}

    public function approve(Request $request, ValidationProof $proof): RedirectResponse
    {
        try {
            $record = $this->proofs->approve($proof, $request->user());
        } catch (InterventionRuleViolation $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        return back()->with('status', "Approved: {$record->auditRecordLabel()} is now ".InterventionRecord::validationLabel($record->validation_status).'.');
    }

    public function reject(Request $request, ValidationProof $proof): RedirectResponse
    {
        try {
            $this->proofs->reject($proof, (string) $request->input('reason', ''), $request->user());
        } catch (InterventionRuleViolation $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        return back()->with('status', "Rejected: {$proof->record->auditRecordLabel()} stays ".InterventionRecord::validationLabel($proof->record->validation_status).'.');
    }

    /** The proof file is private: signed-in staff who validate or approve only. */
    public function file(ValidationProof $proof): StreamedResponse
    {
        $disk = Storage::disk(DamagePhoto::disk());
        abort_unless($disk->exists($proof->path), 404);

        return $disk->response($proof->path, $proof->original_name, ['Cache-Control' => 'private, no-store'], 'inline');
    }
}
