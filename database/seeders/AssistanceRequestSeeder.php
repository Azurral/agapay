<?php

namespace Database\Seeders;

use App\Models\AssistanceRequest;
use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\Disaster;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample assistance requests for the LGU Requests tab: mostly after Typhoon Cristina, across barangays.
 * Approved ones point at program records the earlier seeders made, so no extra records appear.
 */
class AssistanceRequestSeeder extends Seeder
{
    public function run(): void
    {
        $encoder = User::where('username', 'Encoder_03')->value('id');
        $agritech = User::where('username', 'Agritech_02')->value('id');
        $admin = User::where('username', 'Admin_01')->value('id');

        // [first, last, source, program, crisis, crop, qty, status, decision note, filed]
        $requests = [
            ['Juan', 'Dela Cruz', 'lgu', 'Emergency Seedlings', 'Typhoon Cristina', 'Rice', 10, 'pending', null, '2026-07-22'],
            ['Maria', 'Dela Cruz', 'lgu', 'Emergency Seedlings', 'Typhoon Cristina', 'Cabbage', 5, 'pending', null, '2026-07-22'],
            ['Liza', 'Domingo', 'lgu', 'Emergency Seedlings', 'Typhoon Cristina', 'Sweet Potato', 5, 'pending', null, '2026-07-23'],
            ['Maria', 'Santos', 'lgu', 'Complete Fertilizer', 'Typhoon Cristina', 'Rice', 2, 'approved', null, '2026-07-21'],
            ['Rosa', 'Mendez', 'da', 'Organic Liquid Fertilizer', 'Typhoon Cristina', 'Cabbage', 5, 'approved', null, '2026-07-21'],
            ['Pedro', 'Reyes', 'lgu', 'Emergency Seedlings', 'Rain-Induced Landslide', 'Corn', 10, 'approved', null, '2026-07-05'],
            ['Lorna', 'Reyes', 'lgu', 'Emergency Seedlings', 'Typhoon Cristina', 'Corn', 10, 'pending', null, '2026-07-24'],
            ['Carlos', 'Ibanez', 'da', 'Certified Rice Seeds', 'Typhoon Cristina', 'Rice', 2, 'pending', null, '2026-07-24'],
            ['Teresa', 'Ibanez', 'da', 'Certified Rice Seeds', 'Rain-Induced Landslide', 'Rice', 2, 'pending', null, '2026-07-06'],
            ['Ana', 'Gomez', 'lgu', 'Municipal Cash Subsidy', 'El Niño Drought', null, null, 'denied', 'Already listed for the Municipal Cash Subsidy this cycle.', '2026-04-20'],
            ['Federico', 'Wasing', 'da', 'Certified Rice Seeds', 'Frost (Cold Spell)', 'Rice', 2, 'denied', 'No RSBSA No.; DA programs need one.', '2026-01-28'],
            ['Estrella', 'Domogen', 'lgu', 'Emergency Seedlings', 'Fall Armyworm Infestation', 'Corn', 5, 'pending', null, '2026-05-30'],
            ['Ana', 'Dela Cruz', 'lgu', 'Municipal Cash Subsidy', 'Typhoon Cristina', null, null, 'pending', null, '2026-07-25'],
            ['Juan', 'Dela Cruz', 'da', 'Complete Fertilizer', 'Rice Black Bug Infestation', 'Rice', 2, 'pending', null, '2026-05-12'],
            ['Maria', 'Santos', 'lgu', 'Emergency Seedlings', 'Southwest Monsoon Flooding', 'Rice', 10, 'denied', 'Seedlings ran out for Samoki; file again next cycle.', '2026-06-15'],
        ];

        foreach ($requests as $i => [$first, $last, $source, $program, $crisis, $crop, $qty, $status, $note, $filed]) {
            $beneficiary = Beneficiary::withTrashed()->where(['first_name' => $first, 'last_name' => $last])->firstOrFail();
            $intervention = Intervention::where(['source' => $source, 'name' => $program])->firstOrFail();
            $record = $status === AssistanceRequest::APPROVED
                ? InterventionRecord::where(['beneficiary_id' => $beneficiary->id, 'intervention_id' => $intervention->id])->value('id')
                : null;

            $request = AssistanceRequest::firstOrNew([
                'beneficiary_id' => $beneficiary->id, 'intervention_id' => $intervention->id,
                'disaster_id' => Disaster::where('name', $crisis)->value('id'),
            ]);
            $request->fill([
                'crop_id' => $crop ? Crop::where('name', $crop)->value('id') : null,
                'quantity' => $qty,
                'reason' => $crisis ? "Crops damaged by the {$crisis}" : null,
                'status' => $status,
                'decision_note' => $note,
                'decided_by' => $status === AssistanceRequest::PENDING ? null : $admin,
                'decided_at' => $status === AssistanceRequest::PENDING ? null : $filed.' 15:00:00',
                'intervention_record_id' => $record,
                'created_by' => $i % 2 === 0 ? $encoder : $agritech,
            ]);
            $request->created_at = $filed.' 09:00:00';
            $request->save();
        }
    }
}
