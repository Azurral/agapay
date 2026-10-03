<?php

namespace Database\Seeders;

use App\Models\DistributionCycle;
use App\Models\Intervention;
use Illuminate\Database\Seeder;

/** OMAG's DA and LGU programs (paper §1) and the 2026 distribution cycles. */
class InterventionSeeder extends Seeder
{
    /** [source, name, unit, one_per_household, allow_repeat] */
    public const PROGRAMS = [
        ['da', 'Certified Rice Seeds', 'sack', false, false],
        ['da', 'Organic Liquid Fertilizer', 'L', false, false],
        ['da', 'Complete Fertilizer', 'sack', false, false],
        ['da', 'PAFF', null, true, false],
        ['da', 'RFFA', null, true, false],
        ['da', 'HDPE Pipes', 'meter', false, false],
        ['da', 'Molasses', 'L', false, false],
        ['da', 'Agri Machinery', 'unit', true, false],
        ['lgu', 'Complete Fertilizer', 'sack', false, false],
        ['lgu', 'Emergency Seedlings', 'bundle', false, true],
        ['lgu', 'Municipal Cash Subsidy', null, true, false],
    ];

    /** [code, label, schedule date, status] */
    public const CYCLES = [
        ['2026-Q1', '2026-Q1 Wet Season', '2026-03-10', DistributionCycle::STATUS_COMPLETED],
        ['2026-Q2', '2026-Q2 Planting Season', '2026-06-10', DistributionCycle::STATUS_COMPLETED],
        ['2026-Q3', '2026-Q3 Dry Season', '2026-08-14', DistributionCycle::STATUS_ONGOING],
    ];

    public function run(): void
    {
        foreach (self::PROGRAMS as [$source, $name, $unit, $onePerHousehold, $allowRepeat]) {
            Intervention::updateOrCreate(['source' => $source, 'name' => $name], [
                'unit' => $unit, 'one_per_household' => $onePerHousehold, 'allow_repeat' => $allowRepeat, 'is_active' => true,
            ]);
        }

        foreach (self::CYCLES as [$code, $label, $date, $status]) {
            DistributionCycle::updateOrCreate(['code' => $code], [
                'label' => $label, 'schedule_date' => $date, 'venue' => 'OMAG Bontoc Covered Court', 'status' => $status,
            ]);
        }
    }
}
