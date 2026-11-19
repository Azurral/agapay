<?php

namespace App\Services;

use App\Exceptions\InterventionRuleViolation;
use App\Models\Beneficiary;
use App\Models\DamagePhoto;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Models\ValidationProof;
use App\Support\IdCheck;
use App\Support\IdTextReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Proof of eligibility (panel, sir Ginard: "even if it is verified there is no proof; the encoder can fake it"): a
 * change of a record's validation status needs a proof file. AGAPAY checks what it can (a photo or PDF, not reused for
 * another farmer, the farmer's name printed on it); the Administrator approves the change after seeing the proof.
 */
final class ValidationProofService
{
    public function __construct(private readonly ClaimService $claims, private readonly IdTextReader $reader) {}

    /**
     * The status the user picked: unchanged needs nothing; a change needs a proof file and waits for the
     * Administrator, unless the Administrator made it.
     *
     * @return array{record: InterventionRecord, proof: ?ValidationProof}
     */
    public function change(InterventionRecord $record, string $status, ?UploadedFile $file, User $actor): array
    {
        if ($status === $record->validation_status) {
            return ['record' => $this->claims->validate($record, $status, $actor), 'proof' => null];
        }

        Validator::make(['validation_status' => $status, 'proof' => $file], [
            'validation_status' => ['required', 'in:'.implode(',', array_keys(InterventionRecord::VALIDATIONS))],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ], [
            'validation_status.*' => 'Choose a validation status from the list.',
            'proof.required' => 'Attach the proof for this status (e.g. a signed barangay certification) as a photo or PDF.',
            'proof.mimes' => 'Attach the proof as a photo (JPG or PNG) or a PDF.',
            'proof.max' => 'The proof file must be 8 MB or smaller.',
        ])->validate();

        if ($record->pendingProof()->exists()) {
            throw new InterventionRuleViolation('A status change for this record is already waiting for the Administrator.');
        }
        $sha = hash_file('sha256', $file->getRealPath());
        $used = ValidationProof::where('sha256', $sha)->with('record.beneficiary')->first()?->record?->beneficiary ?? Beneficiary::holderOfIdPhoto($sha);
        if ($used !== null) {
            throw ValidationException::withMessages(['proof' => "This proof file was already used for {$used->fullName()}. Attach the proof for this farmer."]);
        }

        $path = $file->store('validation-proofs', DamagePhoto::disk());
        try {
            return DB::transaction(function () use ($record, $status, $file, $actor, $path, $sha) {
                $proof = ValidationProof::create([
                    'intervention_record_id' => $record->id,
                    'from_status' => $record->validation_status,
                    'to_status' => $status,
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName() ?: 'proof', 0, 255),
                    'sha256' => $sha,
                    'checks' => $this->checks($file, $record->beneficiary),
                    'status' => ValidationProof::PENDING,
                    'submitted_by' => $actor->id,
                ]);

                // The Administrator decides these changes, so their own is approved at once.
                if ($actor->can('beneficiaries.approve')) {
                    return ['record' => $this->approve($proof, $actor), 'proof' => $proof->fresh()];
                }

                return ['record' => $record, 'proof' => $proof];
            });
        } catch (Throwable $e) {
            Storage::disk(DamagePhoto::disk())->delete($path);

            throw $e;
        }
    }

    public function approve(ValidationProof $proof, User $admin): InterventionRecord
    {
        return DB::transaction(function () use ($proof, $admin) {
            $proof = $this->lockPending($proof);
            $record = $this->claims->validate($proof->record, $proof->to_status, $admin);
            $proof->update(['status' => ValidationProof::APPROVED, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);

            return $record;
        });
    }

    public function reject(ValidationProof $proof, string $reason, User $admin): ValidationProof
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Say why the proof is rejected.']);
        }

        return DB::transaction(function () use ($proof, $reason, $admin) {
            $proof = $this->lockPending($proof);
            $proof->update(['status' => ValidationProof::REJECTED, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_note' => mb_substr($reason, 0, 500)]);

            return $proof;
        });
    }

    private function lockPending(ValidationProof $proof): ValidationProof
    {
        $proof = ValidationProof::lockForUpdate()->findOrFail($proof->id);
        if ($proof->status !== ValidationProof::PENDING) {
            throw new InterventionRuleViolation('This status change was already decided.');
        }

        return $proof;
    }

    /** @return list<array{check: string, result: string}> */
    private function checks(UploadedFile $file, Beneficiary $farmer): array
    {
        $isPdf = strtolower($file->guessExtension() ?? '') === 'pdf';
        $checks = [['check' => 'A photo or PDF, not used for another farmer', 'result' => IdCheck::PASS]];
        $text = $isPdf ? null : $this->reader->read($file->getRealPath());
        if ($text === null) {
            $why = $isPdf ? 'PDF: check by eye' : ($this->reader->available() ? 'text could not be read' : 'text reader (OCR) not installed');

            return [...$checks, ['check' => "Name on the proof ({$why})", 'result' => IdCheck::UNKNOWN]];
        }

        return [...$checks, [
            'check' => "Name {$farmer->first_name} {$farmer->last_name} printed on the proof",
            'result' => IdCheck::nameFound($text, $farmer->first_name, $farmer->last_name) ? IdCheck::PASS : IdCheck::FAIL,
        ]];
    }
}
