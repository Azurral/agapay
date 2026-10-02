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

        if (! in_array($beneficiary->rsbsa_status, $from, true)) {
            throw new InvalidRsbsaTransition(
                "{$beneficiary->fullName()} is ".Beneficiary::rsbsaStatusLabel($beneficiary->rsbsa_status).' and cannot be '.self::pastTense($action).'.'
            );
        }

        $old = $beneficiary->only(['rsbsa_status', 'rsbsa_number', 'rsbsa_status_reason']);

        DB::transaction(function () use ($beneficiary, $action, $input, $to) {
            match ($action) {
                'record-number' => $beneficiary->rsbsa_number = self::validatedNumber($beneficiary, $input),
                'return' => $beneficiary->forceFill(['rsbsa_status_reason' => $reason = self::validatedReason($input), 'encoding_issue' => $reason]),
                'reject' => $beneficiary->rsbsa_status_reason = self::validatedReason($input),
                'resubmit' => $beneficiary->forceFill(['rsbsa_status_reason' => null, 'encoding_issue' => null]),
                default => null,
            };

            $beneficiary->rsbsa_status = $to;
            $beneficiary->updated_by = auth()->id() ?? $beneficiary->updated_by;
            $beneficiary->saveQuietly();
        });

        AuditLogger::record($label, $beneficiary, null, $old, $beneficiary->only(array_keys($old)));

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

        return $number;
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
