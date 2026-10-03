<?php

namespace Database\Factories;

use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use App\Services\DamageCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamageReport>
 */
class DamageReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'disaster_id' => fn () => Disaster::firstOrCreate(['name' => 'Typhoon Cristina'], ['occurred_on' => '2026-07-20'])->id,
            'beneficiary_id' => Beneficiary::factory(),
            'barangay_id' => fn (array $attributes) => Beneficiary::find($attributes['beneficiary_id'])->barangay_id,
            'crop_id' => fn () => Crop::firstOrCreate(['name' => 'Rice'], ['yield_mt_per_ha' => 4, 'price_per_mt' => 20000, 'partial_loss_factor' => 0.5])->id,
            'crop_stage' => 'vegetative',
            'total_area_ha' => 1.00,
            'partial_area_ha' => 0.00,
            'yield_mt_per_ha' => 4.00,
            'price_per_mt' => 20000,
            'partial_loss_factor' => 0.50,
            'loss_mt' => 4.00,
            'cost' => 80000,
            'status' => DamageReport::FOR_VALIDATION,
            'reported_by' => User::factory(),
        ];
    }

    /** Areas with loss and cost worked out from the report's crop. */
    public function areas(float $totalHa, float $partialHa): static
    {
        return $this->state(fn (array $attributes) => [
            'total_area_ha' => $totalHa,
            'partial_area_ha' => $partialHa,
            ...DamageCalculator::forCrop(Crop::findOrFail($attributes['crop_id']), $totalHa, $partialHa),
        ]);
    }
}
