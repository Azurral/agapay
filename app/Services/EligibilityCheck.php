<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\InterventionRecord;

/**
 * The automatic part of eligibility verification (paper 2.1.2: "automate eligibility verification").
 * It only flags what the records can show; a person still confirms or tags the record (paper 1.4.2:
 * the system does not decide eligibility on its own).
 */
final class EligibilityCheck
{
    /** Tags on an earlier record that make a person look ineligible now. */
    private const EARLIER_TAGS = [InterventionRecord::VALIDATION_DECEASED, InterventionRecord::VALIDATION_INACTIVE, InterventionRecord::VALIDATION_RELOCATED];

    public function __construct(private readonly ClaimService $claims, private readonly InterventionAssignment $assignment) {}

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function run(InterventionRecord $record): array
    {
        $record->loadMissing(['beneficiary', 'intervention', 'cycle']);
        $person = $record->beneficiary;
        $program = $record->intervention;

        $age = $person->birthdate ? $person->age() : null;
        $twin = $this->possibleDuplicate($person);
        $tagged = InterventionRecord::with('cycle')->where('beneficiary_id', $person->id)->whereKeyNot($record->id)
            ->whereIn('validation_status', self::EARLIER_TAGS)->latest('id')->first();
        $household = $this->claims->householdClaim($record);
        $repeat = $this->assignment->earlierClaim($person->id, $program, $record->distribution_cycle_id, $record->id);

        return [
            $this->check('18 years old or older', $age !== null && $age >= 18,
                $age === null ? 'No birthdate on file.' : "{$age} years old."),
            $this->check('RSBSA No. on file (needed for DA programs)', ! $program->requiresRsbsa() || (bool) $person->rsbsa_number,
                $person->rsbsa_number ?: ($program->requiresRsbsa() ? 'Missing: DA programs need one.' : 'None; not needed for LGU programs.')),
            $this->check('No other profile with the same name and birthdate', $twin === null,
                $twin ? "Possible duplicate: {$twin->fullName()} ({$twin->barangay?->name})." : 'No match found.'),
            $this->check('Household has not received this program this cycle', $household === null,
                $household ? "{$household->beneficiary->fullName()} already claimed it in {$record->cycle->code}." : 'No other claim in the household.'),
            $this->check('Not tagged Deceased, Inactive or Relocated before', $tagged === null,
                $tagged ? 'Tagged '.InterventionRecord::validationLabel($tagged->validation_status)." in {$tagged->cycle->code}." : 'No earlier tag.'),
            $this->check('Not a repeat of LGU assistance already received', $repeat === null,
                $repeat ? "Already received in {$repeat->cycle->code}." : 'No earlier claim of this assistance.'),
        ];
    }

    public function passes(InterventionRecord $record): bool
    {
        return collect($this->run($record))->every(fn (array $check) => $check['ok']);
    }

    private function check(string $label, bool $ok, string $detail): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }

    /** Another active profile with the same name (any letter case or spacing) and birthdate, in any barangay. */
    private function possibleDuplicate(Beneficiary $person): ?Beneficiary
    {
        if (! $person->birthdate) {
            return null;
        }
        $squash = fn (string $v) => mb_strtolower(preg_replace('/\s+/', '', $v));

        return Beneficiary::with('barangay:id,name')->whereKeyNot($person->id)
            ->whereDate('birthdate', $person->birthdate)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'barangay_id'])
            ->first(fn (Beneficiary $b) => $squash($b->first_name) === $squash($person->first_name) && $squash($b->last_name) === $squash($person->last_name));
    }
}
