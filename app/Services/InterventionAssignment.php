<?php

namespace App\Services;

use App\Exceptions\InterventionRuleViolation;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Creates and edits intervention records: one per beneficiary, intervention and cycle; LGU repeats flagged Duplicate. */
final class InterventionAssignment
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** @param array{quantity?: float|int|string|null, date_distributed?: string|null} $attrs */
    public function assign(Beneficiary $beneficiary, Intervention $intervention, DistributionCycle $cycle, array $attrs, User $actor): InterventionRecord
    {
        if ($beneficiary->trashed()) {
            throw new InterventionRuleViolation('This beneficiary is archived.');
        }

        return DB::transaction(function () use ($beneficiary, $intervention, $cycle, $attrs, $actor) {
            // One assignment per beneficiary at a time, so a double-submitted form cannot fill the same slot twice.
            $current = Beneficiary::whereKey($beneficiary->id)->lockForUpdate()->first() ?? $beneficiary;
            self::ensureRsbsaFor($current, $intervention);
            $this->ensureSlotFree($beneficiary, $intervention, $cycle);

            return InterventionRecord::create([
                'beneficiary_id' => $beneficiary->id,
                'intervention_id' => $intervention->id,
                'distribution_cycle_id' => $cycle->id,
                'quantity' => $attrs['quantity'] ?? null,
                'date_distributed' => $attrs['date_distributed'] ?? null,
                'validation_status' => $this->isRepeat($beneficiary->id, $intervention, $cycle->id)
                    ? InterventionRecord::VALIDATION_DUPLICATE
                    : InterventionRecord::VALIDATION_ELIGIBLE,
                'claim_status' => InterventionRecord::CLAIM_UNCLAIMED,
                'created_by' => $actor->id,
            ]);
        });
    }

    /** @param array{quantity?: float|int|string|null, date_distributed?: string|null, intervention_id?: int, distribution_cycle_id?: int} $attrs */
    public function reassign(InterventionRecord $record, array $attrs, User $actor): InterventionRecord
    {
        return DB::transaction(function () use ($record, $attrs, $actor) {
            // Work on the current row, not the copy the caller loaded: someone may have unclaimed or archived it since.
            $record = InterventionRecord::withTrashed()->lockForUpdate()->findOrFail($record->id);
            if ($record->trashed()) {
                throw new InterventionRuleViolation('This record is archived.');
            }

            $interventionId = (int) ($attrs['intervention_id'] ?? $record->intervention_id);
            $cycleId = (int) ($attrs['distribution_cycle_id'] ?? $record->distribution_cycle_id);
            $moved = $interventionId !== $record->intervention_id || $cycleId !== $record->distribution_cycle_id;

            if ($moved && $record->isClaimed()) {
                throw new InterventionRuleViolation('Unclaim it first.');
            }

            $record->fill(Arr::only($attrs, ['quantity', 'date_distributed']));

            if ($moved) {
                $intervention = Intervention::findOrFail($interventionId);
                self::ensureRsbsaFor($record->beneficiary, $intervention);
                $this->ensureSlotFree($record->beneficiary, $intervention, DistributionCycle::findOrFail($cycleId), $record->id);
                $record->fill(['intervention_id' => $interventionId, 'distribution_cycle_id' => $cycleId]);

                if ($this->isRepeat($record->beneficiary_id, $intervention, $cycleId, $record->id)) {
                    $record->validation_status = InterventionRecord::VALIDATION_DUPLICATE;
                } elseif ($record->validation_status === InterventionRecord::VALIDATION_DUPLICATE) {
                    $record->validation_status = InterventionRecord::VALIDATION_ELIGIBLE;
                }
            }

            $record->save();
            $this->inventory->syncRecord($record, $actor, 'quantity reduced');

            return $record;
        });
    }

    /** DA programs need an RSBSA number; LGU programs also serve farmers without one. */
    public static function ensureRsbsaFor(Beneficiary $beneficiary, Intervention $intervention): void
    {
        if ($intervention->requiresRsbsa() && ! $beneficiary->rsbsa_number) {
            throw new InterventionRuleViolation(Intervention::rsbsaRequiredFor($beneficiary));
        }
    }

    private function ensureSlotFree(Beneficiary $beneficiary, Intervention $intervention, DistributionCycle $cycle, ?int $ignoreId = null): void
    {
        $taken = InterventionRecord::where([
            'beneficiary_id' => $beneficiary->id,
            'intervention_id' => $intervention->id,
            'distribution_cycle_id' => $cycle->id,
        ])->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->lockForUpdate()->exists();

        if ($taken) {
            throw new InterventionRuleViolation("{$beneficiary->fullName()} already has {$intervention->name} in {$cycle->code}.");
        }
    }

    private function isRepeat(int $beneficiaryId, Intervention $intervention, int $cycleId, ?int $ignoreId = null): bool
    {
        return $this->earlierClaim($beneficiaryId, $intervention, $cycleId, $ignoreId) !== null;
    }

    /**
     * The beneficiary's claimed record of the same LGU assistance in another cycle — a repeat, unless the program allows repeats.
     * Assignment flags it "Duplicate"; ClaimService refuses to pay it out or restore it.
     */
    public function earlierClaim(int $beneficiaryId, Intervention $intervention, int $cycleId, ?int $ignoreId = null): ?InterventionRecord
    {
        if ($intervention->source !== Intervention::SOURCE_LGU || $intervention->allow_repeat) {
            return null;
        }

        return InterventionRecord::with('cycle')
            ->where(['beneficiary_id' => $beneficiaryId, 'intervention_id' => $intervention->id])
            ->where('distribution_cycle_id', '!=', $cycleId)
            ->where('claim_status', InterventionRecord::CLAIM_CLAIMED)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();
    }
}
