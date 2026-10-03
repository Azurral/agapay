<?php

namespace App\Services;

use App\Exceptions\InvalidRsbsaTransition;
use App\Models\Beneficiary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** RSBSA application lifecycle: applicant → OMAG validation → DA-RFO endorsement → masterlist number. */
final class RsbsaWorkflow
{
    public const ACTIONS = ['validate', 'endorse', 'record-number', 'return', 'reject', 'resubmit'];

    /** action => [allowed from statuses, target status, audit label] */
    private const TRANSITIONS = [
        'validate' => [[Beneficiary::RSBSA_PENDING], Beneficiary::RSBSA_VALIDATED, 'Validated RSBSA Registration'],
        'endorse' => [[Beneficiary::RSBSA_VALIDATED], Beneficiary::RSBSA_ENDORSED, 'Endorsed RSBSA Registration to DA-RFO'],
        'record-number' => [[Beneficiary::RSBSA_ENDORSED], Beneficiary::RSBSA_REGISTERED, 'Recorded RSBSA Number'],
        'return' => [Beneficiary::RSBSA_IN_PROGRESS, Beneficiary::RSBSA_RETURNED, 'Returned RSBSA Registration'],
        'reject' => [[...Beneficiary::RSBSA_IN_PROGRESS, Beneficiary::RSBSA_RETURNED], Beneficiary::RSBSA_REJECTED, 'Rejected RSBSA Registration'],
        'resubmit' => [[Beneficiary::RSBSA_RETURNED], Beneficiary::RSBSA_PENDING, 'Resubmitted RSBSA Registration'],
    ];

    /**
     * @throws InvalidRsbsaTransition when the step is not allowed from the current status
     * @throws ValidationException when required input is missing or invalid
     */
    public static function apply(Beneficiary $beneficiary, string $action, array $input = []): Beneficiary
    {
        if (! isset(self::TRANSITIONS[$action])) {
            throw new InvalidRsbsaTransition('Unknown RSBSA action.');
        }

        [$from, $to, $label] = self::TRANSITIONS[$action];

        // The row is locked and its status re-read, so two staff acting on one application cannot both succeed;
        // the audit row is part of the same transaction.
        $current = DB::transaction(function () use ($beneficiary, $action, $input, $from, $to, $label) {
            $current = Beneficiary::whereKey($beneficiary->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($current->rsbsa_status, $from, true)) {
                throw new InvalidRsbsaTransition(
                    "{$current->fullName()} is ".Beneficiary::rsbsaStatusLabel($current->rsbsa_status).' and cannot be '.self::pastTense($action).'.'
                );
            }

            $old = $current->only(['rsbsa_status', 'rsbsa_number', 'rsbsa_status_reason']);
            match ($action) {
                'record-number' => $current->rsbsa_number = self::validatedNumber($current, $input),
                'return' => $current->forceFill(['rsbsa_status_reason' => $reason = self::validatedReason($input), 'encoding_issue' => $reason]),
                'reject' => $current->rsbsa_status_reason = self::validatedReason($input),
                'resubmit' => $current->forceFill(['rsbsa_status_reason' => null, 'encoding_issue' => null]),
                default => null,
            };

            $current->rsbsa_status = $to;
            $current->updated_by = auth()->id() ?? $current->updated_by;
            $current->saveQuietly();
            AuditLogger::record($label, $current, null, $old, $current->only(array_keys($old)));

            return $current;
        });

        $beneficiary->setRawAttributes($current->getAttributes(), true);

        return $beneficiary;
    }

    private static function validatedReason(array $input): string
    {
        return Validator::make($input, ['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => 'Enter a reason.',
        ])->validate()['reason'];
    }

    private static function validatedNumber(Beneficiary $beneficiary, array $input): string
    {
        $input['rsbsa_number'] = trim((string) ($input['rsbsa_number'] ?? ''));

        $number = Validator::make($input, ['rsbsa_number' => ['required', 'string', 'max:50']], [
            'rsbsa_number.required' => 'Enter the RSBSA number from the DA-RFO masterlist.',
        ])->validate()['rsbsa_number'];

        $taken = Beneficiary::withTrashed()
            ->whereKeyNot($beneficiary->getKey())
            ->whereRaw('LOWER(TRIM(rsbsa_number)) = ?', [mb_strtolower($number)])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['rsbsa_number' => "RSBSA number {$number} is already assigned to another beneficiary."]);
        }

        return Beneficiary::normalizeRsbsa($number);   // saved quietly, so the model hook does not run
    }

    private static function pastTense(string $action): string
    {
        return match ($action) {
            'validate' => 'validated',
            'endorse' => 'endorsed',
            'record-number' => 'given an RSBSA number',
            'return' => 'returned',
            'reject' => 'rejected',
            'resubmit' => 'resubmitted',
        };
    }
}
