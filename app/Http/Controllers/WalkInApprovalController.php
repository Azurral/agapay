<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\DamagePhoto;
use App\Models\ValidationProof;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * No ghost farmers (panel): the Administrator checks each walk-in registration's valid ID and approves it, or rejects
 * it with a reason. Until then the farmer cannot be put on any program.
 */
class WalkInApprovalController extends Controller
{
    public function index(): View
    {
        return view('beneficiaries.approvals', [
            'pending' => Beneficiary::with(['barangay:id,name', 'creator:id,username'])
                ->where('verification_status', Beneficiary::VERIFICATION_PENDING)->oldest()->oldest('id')->get(),
            'rejected' => Beneficiary::with(['barangay:id,name', 'verifier:id,username'])
                ->where('verification_status', Beneficiary::VERIFICATION_REJECTED)->latest('verified_at')->limit(20)->get(),
            // Validation status changes sent with proof by the Agricultural Technologists.
            'proofs' => ValidationProof::with(['record.beneficiary', 'record.intervention', 'record.cycle', 'submitter:id,username'])
                ->where('status', ValidationProof::PENDING)->oldest()->oldest('id')->get(),
        ]);
    }

    public function approve(Request $request, Beneficiary $beneficiary): RedirectResponse
    {
        $old = $beneficiary->only(['verification_status']);
        $beneficiary->forceFill([
            'verification_status' => Beneficiary::VERIFICATION_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->saveQuietly();
        AuditLogger::record('Approved Walk-in Registration', $beneficiary, null, $old, $beneficiary->only(['verification_status']));

        return back()->with('status', "{$beneficiary->fullName()} was approved.");
    }

    public function reject(Request $request, Beneficiary $beneficiary): RedirectResponse
    {
        $reason = trim((string) $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'Write why the registration is rejected.'],
        )['reason']);

        $old = $beneficiary->only(['verification_status']);
        $beneficiary->forceFill([
            'verification_status' => Beneficiary::VERIFICATION_REJECTED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ])->saveQuietly();
        AuditLogger::record('Rejected Walk-in Registration', $beneficiary, null, $old, ['verification_status' => 'rejected', 'reason' => $reason]);

        return back()->with('status', "{$beneficiary->fullName()} was rejected.");
    }

    /** The ID photo is private: shown only to signed-in staff who may see profiles. */
    public function photo(Beneficiary $beneficiary): StreamedResponse
    {
        return $this->idPhoto($beneficiary->valid_id_photo, 'valid-id-'.$beneficiary->id);
    }

    /** The RSBSA / agricultural ID with the farmer's signature, private like the valid ID. */
    public function rsbsaPhoto(Beneficiary $beneficiary): StreamedResponse
    {
        return $this->idPhoto($beneficiary->rsbsa_id_photo, 'rsbsa-id-'.$beneficiary->id);
    }

    private function idPhoto(?string $path, string $name): StreamedResponse
    {
        $disk = Storage::disk(DamagePhoto::disk());
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, $name.'.'.pathinfo($path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store'], 'inline');
    }
}
