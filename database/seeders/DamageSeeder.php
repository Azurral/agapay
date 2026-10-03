<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use App\Services\DamageCalculator;
use Illuminate\Database\Seeder;

/** Crop reference values, two disasters and the six Figma 329:2978 sample reports (no photos). */
class DamageSeeder extends Seeder
{
    public function run(): void
    {
        $crops = [
            'Rice' => [4.00, 20000], 'Corn' => [3.50, 15000], 'Cabbage' => [20.00, 15000], 'Carrots' => [18.00, 25000],
            'Potato' => [15.00, 30000], 'Beans' => [2.00, 60000], 'Sweet Potato' => [10.00, 20000],
        ];
        foreach ($crops as $name => [$yield, $price]) {
            Crop::updateOrCreate(['name' => $name], ['yield_mt_per_ha' => $yield, 'price_per_mt' => $price, 'partial_loss_factor' => 0.50]);
        }

        Disaster::updateOrCreate(['name' => 'Southwest Monsoon Flooding'], ['occurred_on' => '2026-06-12']);
        $typhoon = Disaster::updateOrCreate(['name' => 'Typhoon Cristina'], ['occurred_on' => '2026-07-20']);

        $agritech = User::where('username', 'Agritech_02')->firstOrFail();
        $encoder = User::where('username', 'Encoder_03')->firstOrFail();

        // [first, last, crop, stage, total ha, partial ha, validated, filed]
        $reports = [
            ['Juan', 'Dela Cruz', 'Rice', 'reproductive', 1.20, 0.30, true, '2026-07-21 09:10:00'],
            ['Maria', 'Santos', 'Rice', 'vegetative', 0.80, 0.20, true, '2026-07-21 10:25:00'],
            ['Carlos', 'Ibanez', 'Rice', 'maturing', 1.50, 0.00, false, '2026-07-21 14:40:00'],
            ['Rosa', 'Mendez', 'Cabbage', 'vegetative', 0.60, 0.10, true, '2026-07-22 08:15:00'],
            ['Lorna', 'Reyes', 'Corn', 'reproductive', 1.00, 0.40, false, '2026-07-22 09:30:00'],
            ['Liza', 'Domingo', 'Sweet Potato', 'harvested', 0.90, 0.00, true, '2026-07-22 11:05:00'],
        ];

        foreach ($reports as [$first, $last, $cropName, $stage, $total, $partial, $validated, $filed]) {
            $beneficiary = Beneficiary::where(['first_name' => $first, 'last_name' => $last])->firstOrFail();
            $crop = Crop::where('name', $cropName)->firstOrFail();

            DamageReport::updateOrCreate(
                ['disaster_id' => $typhoon->id, 'beneficiary_id' => $beneficiary->id, 'crop_id' => $crop->id],
                [
                    'barangay_id' => $beneficiary->barangay_id,
                    'farm_location' => $beneficiary->farm_location,
                    'crop_stage' => $stage,
                    'total_area_ha' => $total,
                    'partial_area_ha' => $partial,
                    ...DamageCalculator::forCrop($crop, $total, $partial),
                    'status' => $validated ? DamageReport::VALIDATED : DamageReport::FOR_VALIDATION,
                    'reported_by' => $encoder->id,
                    'validated_by' => $validated ? $agritech->id : null,
                    'validated_at' => $validated ? '2026-07-23 10:00:00' : null,
                    'created_at' => $filed,
                    'updated_at' => $filed,
                ],
            );
        }
    }
}
