<?php

namespace Database\Factories;

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterventionRecord>
 */
class InterventionRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'intervention_id' => fn () => Intervention::where('source', Intervention::SOURCE_DA)->orderBy('id')->value('id')
                ?? Intervention::create(['source' => Intervention::SOURCE_DA, 'name' => 'Certified Rice Seeds', 'unit' => 'sack'])->id,
            'distribution_cycle_id' => fn () => DistributionCycle::current()?->id
                ?? DistributionCycle::create(['code' => '2026-Q3', 'label' => '2026-Q3 Dry Season', 'status' => DistributionCycle::STATUS_ONGOING])->id,
            'quantity' => 1,
            'validation_status' => InterventionRecord::VALIDATION_PENDING,
            'claim_status' => InterventionRecord::CLAIM_UNCLAIMED,
        ];
    }
}
