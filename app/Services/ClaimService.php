<?php

namespace App\Services;

use App\Exceptions\InterventionRuleViolation;
use App\Models\Beneficiary;
use App\Models\Household;
use App\Models\InterventionRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Eligibility validation, claims, archive and restore of intervention records (paper rules 3, 5, 6). */
final class ClaimService
{
    public function __construct(private readonly InterventionAssignment $assignment, private readonly InventoryService $inventory) {}

    public function validate(InterventionRecord $record, string $status, User $actor): InterventionRecord
    {
        Validator::make(['validation_status' => $status], [
            'validation_status' => ['required', Rule::in(array_keys(InterventionRecord::VALIDATIONS))],
        ], ['validation_status.in' => 'Choose a validation status from the list.'])->validate();

        return DB::transaction(function () use ($record, $status, $actor) {
            $record = $this->lockFresh($record);

            if ($record->isClaimed() && ! in_array($status, InterventionRecord::CLAIMABLE, true)) {
                throw new InterventionRuleViolation('Unclaim it first.');
            }
            // A deceased beneficiary's claim needs a proxy and proof (spec rule 6), also when the status changes after claiming.
            if ($record->isClaimed() && $status === InterventionRecord::VALIDATION_DECEASED
                && (trim((string) $record->proxy_claimant) === '' || trim((string) $record->proof_note) === '')) {
                throw new InterventionRuleViolation('Unclaim it first, or record the proxy.');
            }

            $old = $record->only(['validation_status']);
            $record->forceFill(['validation_status' => $status, 'validated_by' => $actor->id])->saveQuietly();
            AuditLogger::record('Validated Intervention Record', $record, null, $old, $record->only(['validation_status']), $actor);

            return $record;
        });
    }

    /**
     * @param  array{date_distributed?: string|null, quantity?: float|int|string|null, proxy_claimant?: string|null, proof_note?: string|null, override_reason?: string|null}  $input
     * @param  bool  $historical  Encoding a past distribution from a logbook: skips only the pending-validation check.
     */
    public function claim(InterventionRecord $record, User $actor, array $input = [], bool $historical = false): InterventionRecord
    {
        $input = Validator::make($input, [
            'date_distributed' => ['nullable', 'date', 'before_or_equal:today'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'proxy_claimant' => ['nullable', 'string', 'max:255'],
            'proof_note' => ['nullable', 'string', 'max:1000'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ], ['date_distributed.before_or_equal' => 'The distribution date cannot be in the future.'])->validate();

        // Retried a few times: InnoDB may pick a simultaneous claim as a deadlock victim.
        return DB::transaction(function () use ($record, $actor, $input, $historical) {
            $this->lockHousehold($record);
            $record = $this->lockFresh($record);

            if ($record->isClaimed()) {
                throw new InterventionRuleViolation('Already claimed.');
            }

            if ($record->validation_status === InterventionRecord::VALIDATION_PENDING) {
                if (! $historical) {
                    throw new InterventionRuleViolation('Validate eligibility first.');
                }
                $record->validation_status = InterventionRecord::VALIDATION_ELIGIBLE;
            }

            if (! in_array($record->validation_status, InterventionRecord::CLAIMABLE, true)) {
                throw new InterventionRuleViolation('Not eligible: '.InterventionRecord::validationLabel($record->validation_status).'.');
            }

            $this->refuseRepeat($record);

            // Spec rule 7: a stocked program's claim must say how much left the store.
            if ($record->intervention->inventory_item_id && (float) ($input['quantity'] ?? $record->quantity) <= 0) {
                throw new InterventionRuleViolation("Enter the quantity given out: {$record->intervention->name} is deducted from stock.");
            }

            $deceased = $record->validation_status === InterventionRecord::VALIDATION_DECEASED;
            $proxy = trim((string) ($input['proxy_claimant'] ?? ''));
            $proof = trim((string) ($input['proof_note'] ?? ''));
            if ($deceased && ($proxy === '' || $proof === '')) {
                // Only the Administrator's Process Claim has the proxy fields.
                throw new InterventionRuleViolation($actor->role?->slug === Role::ADMIN
                    ? 'A deceased beneficiary can only be claimed by a proxy with a proof note.'
                    : 'Only the Administrator can record a proxy claim for a deceased beneficiary.');
            }

            $override = trim((string) ($input['override_reason'] ?? ''));
            $overridden = false;
            if ($blocker = $this->householdClaim($record)) {
                if ($override === '' || $actor->role?->slug !== Role::ADMIN) {
                    throw $this->householdViolation($record, $blocker);
                }
                $overridden = true;
            }

            $old = $record->getOriginal();
            $record->forceFill([
                'claim_status' => InterventionRecord::CLAIM_CLAIMED,
                'date_distributed' => $input['date_distributed'] ?? today()->toDateString(),
                'quantity' => $input['quantity'] ?? $record->quantity,
                'proxy_claimant' => $deceased ? $proxy : null,
                'proof_note' => $deceased ? $proof : null,
                'override_reason' => $overridden ? $override : null,
                'claimed_by' => $actor->id,
            ])->saveQuietly();

            // Spec rule 7: deduct the stock (throws InsufficientStock, rolling the claim back).
            $this->inventory->syncRecord($record, $actor);

            $keys = ['claim_status', 'validation_status', 'date_distributed', 'proxy_claimant', 'override_reason'];
            AuditLogger::record(
                match (true) {
                    $overridden => 'Claimed Intervention (Household Override)',
                    $historical => 'Claimed Intervention (Historical Encoding)',
                    default => 'Claimed Intervention',
                },
                $record, null, array_intersect_key($old, array_flip($keys)), $record->only($keys), $actor,
            );

            return $record;
        }, attempts: 3);
    }

    public function unclaim(InterventionRecord $record, User $actor): InterventionRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $record = $this->lockFresh($record);

            if (! $record->isClaimed()) {
                throw new InterventionRuleViolation('Not claimed yet.');
            }

            $keys = ['claim_status', 'date_distributed'];
            $old = $record->only($keys);
            $record->forceFill([
                'claim_status' => InterventionRecord::CLAIM_UNCLAIMED,
                'date_distributed' => null, 'proxy_claimant' => null, 'proof_note' => null, 'override_reason' => null, 'claimed_by' => null,
            ])->saveQuietly();

            $this->inventory->syncRecord($record, $actor, 'unclaimed');

            AuditLogger::record('Unclaimed Intervention', $record, null, $old, $record->only($keys), $actor);

            return $record;
        });
    }

    public function archive(InterventionRecord $record, ?string $reason, User $actor): void
    {
        $reason = Validator::make(['reason' => trim((string) $reason)], ['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => 'Enter a reason.',
        ])->validate()['reason'];

        DB::transaction(function () use ($record, $reason, $actor) {
            $record = $this->lockFresh($record);
            $record->forceFill(['delete_reason' => $reason, 'deleted_by' => $actor->id])->saveQuietly();
            $record->delete();   // Auditable: "Archived Intervention Record"
            $this->inventory->syncRecord($record, $actor, 'archived');
        });
    }

    public function restore(InterventionRecord $record, User $actor): InterventionRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $this->lockHousehold($record);
            $record = InterventionRecord::withTrashed()->lockForUpdate()->findOrFail($record->id);

            // A second click finds it already restored; a record of an archived farmer would stay hidden.
            if (! $record->trashed()) {
                throw new InterventionRuleViolation('This record is not archived.');
            }
            if ($record->beneficiary?->trashed()) {
                throw new InterventionRuleViolation("Restore {$record->beneficiary->fullName()}'s profile first.");
            }

            $taken = InterventionRecord::where($record->only(['beneficiary_id', 'intervention_id', 'distribution_cycle_id']))
                ->whereKeyNot($record->id)->exists();
            if ($taken) {
                throw new InterventionRuleViolation('An active record already exists for this intervention and cycle.');
            }

            // A claimed record comes back only if the household and repeat rules still allow its claim.
            if ($record->isClaimed()) {
                $this->refuseRepeat($record);
                if ($blocker = $this->householdClaim($record)) {
                    throw $this->householdViolation($record, $blocker);
                }
            }

            // Restored quietly so the trail gets one "Restored" row, not an extra "Updated" row for deleted_at.
            $record->forceFill(['delete_reason' => null, 'deleted_by' => null, 'deleted_at' => null])->saveQuietly();
            $this->inventory->syncRecord($record, $actor);
            AuditLogger::record('Restored Intervention Record', $record, null, [], [], $actor);

            return $record;
        });
    }

    /** Re-reads the record under a row lock; archived records cannot be acted on. */
    private function lockFresh(InterventionRecord $record): InterventionRecord
    {
        $fresh = InterventionRecord::withTrashed()->lockForUpdate()->findOrFail($record->id);

        if ($fresh->trashed()) {
            throw new InterventionRuleViolation('This record is archived.');
        }

        return $fresh;
    }

    /**
     * Serializes claims within one household: every claim or restore first locks the household row,
     * so two members' simultaneous claims queue up instead of both passing (or deadlocking) on the record rows.
     */
    private function lockHousehold(InterventionRecord $record): void
    {
        $householdId = Beneficiary::withTrashed()->whereKey($record->beneficiary_id)->value('household_id');

        if ($householdId) {
            Household::whereKey($householdId)->lockForUpdate()->first();
        }
    }

    /** The household member's claimed record that blocks this claim, if any (run after lockHousehold). */
    private function householdClaim(InterventionRecord $record): ?InterventionRecord
    {
        $householdId = $record->beneficiary->household_id;

        if (! $record->intervention->one_per_household || ! $householdId) {
            return null;
        }

        return InterventionRecord::with('beneficiary')
            ->where(['intervention_id' => $record->intervention_id, 'distribution_cycle_id' => $record->distribution_cycle_id])
            ->whereIn('beneficiary_id', Beneficiary::where('household_id', $householdId)->select('id'))
            ->where('claim_status', InterventionRecord::CLAIM_CLAIMED)
            ->whereKeyNot($record->id)
            ->first();
    }

    private function householdViolation(InterventionRecord $record, InterventionRecord $blocker): InterventionRuleViolation
    {
        return new InterventionRuleViolation(
            "{$blocker->beneficiary->fullName()} already claimed {$record->intervention->name} for this household in {$record->cycle->code}."
        );
    }

    /** Spec rule 6: identical LGU assistance is not paid out twice unless the program allows repeats. */
    private function refuseRepeat(InterventionRecord $record): void
    {
        $earlier = $this->assignment->earlierClaim($record->beneficiary_id, $record->intervention, $record->distribution_cycle_id, $record->id);

        if ($earlier) {
            throw new InterventionRuleViolation("Not eligible: Duplicate - {$record->intervention->name} was already received in {$earlier->cycle->code}.");
        }
    }
}
