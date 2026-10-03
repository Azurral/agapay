<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** The intervention rows shown in the Figma DA/LGU lists and Archived/Restore tabs. */
class InterventionRecordSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'Admin_01')->value('id');

        // [first, last, source, intervention, cycle, validation, claim, date distributed, qty]
        $active = [
            ['Juan', 'Dela Cruz', 'da', 'Certified Rice Seeds', '2026-Q3', 'eligible', 'claimed', '2026-07-18', 2],
            ['Rosa', 'Mendez', 'da', 'Organic Liquid Fertilizer', '2026-Q2', 'ofw', 'claimed', '2026-07-15', 5],
            ['Carlos', 'Ibanez', 'da', 'Complete Fertilizer', '2026-Q3', 'pending', 'unclaimed', null, 1],
            ['Liza', 'Domingo', 'da', 'PAFF', '2026-Q1', 'bedridden', 'unclaimed', null, null],
            ['Maria', 'Santos', 'lgu', 'Complete Fertilizer', '2026-Q3', 'bedridden', 'unclaimed', null, 1],
            ['Pedro', 'Reyes', 'lgu', 'Emergency Seedlings', '2026-Q3', 'pending', 'unclaimed', null, 10],
            ['Ana', 'Gomez', 'lgu', 'Municipal Cash Subsidy', '2026-Q3', 'duplicate', 'unclaimed', null, null],
        ];

        foreach ($active as [$first, $last, $source, $name, $cycle, $validation, $claim, $date, $qty]) {
            $this->upsert($first, $last, $source, $name, $cycle, [
                'quantity' => $qty, 'validation_status' => $validation, 'claim_status' => $claim,
                'date_distributed' => $date, 'claimed_by' => $claim === 'claimed' ? $admin : null, 'created_by' => $admin,
            ]);
        }

        // [first, last, source, intervention, reason, deleted by, deleted on]
        $archived = [
            ['Federico', 'Wasing', 'da', 'Certified Rice Seeds', 'Deceased - confirmed by barangay', 'Agritech_02', '2026-07-08'],
            ['Estrella', 'Domogen', 'da', 'Complete Fertilizer', 'Data correction - re-entered under new RSBSA no.', 'Encoder_03', '2026-07-03'],
            ['Lorna', 'Reyes', 'lgu', 'Emergency Seedlings', 'Household already claimed via another member', 'Admin_01', '2026-07-12'],
            ['Teresa', 'Ibanez', 'lgu', 'Municipal Cash Subsidy', 'Withdrew registration voluntarily', 'Agritech_02', '2026-07-11'],
        ];

        foreach ($archived as [$first, $last, $source, $name, $reason, $by, $on]) {
            $this->upsert($first, $last, $source, $name, '2026-Q3', [
                'quantity' => 1,
                'created_by' => $admin,
                'delete_reason' => $reason,
                'deleted_by' => User::where('username', $by)->value('id'),
                'deleted_at' => Carbon::parse($on.' 10:00:00'),
            ]);
        }
    }

    private function upsert(string $first, string $last, string $source, string $name, string $cycle, array $values): InterventionRecord
    {
        $record = InterventionRecord::withTrashed()->firstOrNew([
            'beneficiary_id' => Beneficiary::where(['first_name' => $first, 'last_name' => $last])->valueOrFail('id'),
            'intervention_id' => Intervention::where(['source' => $source, 'name' => $name])->valueOrFail('id'),
            'distribution_cycle_id' => DistributionCycle::where('code', $cycle)->valueOrFail('id'),
        ]);
        $record->forceFill($values)->saveQuietly();

        return $record;
    }
}
