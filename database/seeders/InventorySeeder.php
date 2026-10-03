<?php

namespace Database\Seeders;

use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Figma 470:785 stock levels: Certified Rice Seeds 120/86/34, Organic Liquid Fertilizer 250/210/40, Complete Fertilizer 180/150/30, HDPE Pipes 500/320/180. */
class InventorySeeder extends Seeder
{
    /** [name, unit, unit label, low-stock threshold, stock in, stock out, linked interventions (source, name)] */
    public const ITEMS = [
        ['Certified Rice Seeds', 'sack', 'sacks (20kg)', 40, 120, 86, [['da', 'Certified Rice Seeds']]],
        ['Organic Liquid Fertilizer', 'liter', 'liters', 50, 250, 210, [['da', 'Organic Liquid Fertilizer']]],
        ['Complete Fertilizer', 'sack', 'sacks (50kg)', 25, 180, 150, [['da', 'Complete Fertilizer'], ['lgu', 'Complete Fertilizer']]],
        ['HDPE Pipes', 'meter', 'meters', 100, 500, 320, [['da', 'HDPE Pipes']]],
    ];

    public function run(): void
    {
        $admin = User::where('username', 'Admin_01')->value('id');

        // The seeded claims (Juan's rice seeds, Rosa's fertilizer) were deducted automatically.
        $autoOuts = InterventionRecord::where('claim_status', InterventionRecord::CLAIM_CLAIMED)
            ->with(['beneficiary', 'intervention'])->get()
            ->filter(fn (InterventionRecord $r) => $r->quantity > 0);

        foreach (self::ITEMS as [$name, $unit, $label, $threshold, $in, $out, $links]) {
            $item = InventoryItem::updateOrCreate(['name' => $name], ['unit' => $unit, 'unit_label' => $label, 'low_stock_threshold' => $threshold]);

            foreach ($links as [$source, $program]) {
                Intervention::where(['source' => $source, 'name' => $program])->update(['inventory_item_id' => $item->id]);
            }

            $this->movement($item, InventoryMovement::IN, $in, '2026-07-01', 'Delivery from DA-RFO', $admin);

            $autoTotal = 0;
            foreach ($autoOuts->filter(fn (InterventionRecord $r) => collect($links)->contains([$r->intervention->source, $r->intervention->name])) as $record) {
                InventoryMovement::updateOrCreate(
                    ['intervention_record_id' => $record->id, 'source' => InventoryMovement::AUTO, 'direction' => InventoryMovement::OUT],
                    [
                        'inventory_item_id' => $item->id,
                        'quantity' => $record->quantity,
                        'movement_date' => $record->date_distributed,
                        'notes' => "Auto-deducted: distribution to {$record->beneficiary->fullName()} ({$record->beneficiary->rsbsaDisplay()})",
                        'user_id' => $admin,
                    ],
                );
                $autoTotal += (float) $record->quantity;
            }

            // The logbook line makes up the Figma stock-out; a dev database that claimed more than that gets none.
            $logbook = round($out - $autoTotal, 2);
            if ($logbook > 0) {
                $this->movement($item, InventoryMovement::OUT, $logbook, '2026-06-30', 'Distributed before AGAPAY (logbook)', $admin);
            } else {
                InventoryMovement::where(['inventory_item_id' => $item->id, 'source' => InventoryMovement::MANUAL, 'notes' => 'Distributed before AGAPAY (logbook)'])->delete();
            }
        }
    }

    private function movement(InventoryItem $item, string $direction, float $quantity, string $date, string $notes, ?int $userId): void
    {
        InventoryMovement::updateOrCreate(
            ['inventory_item_id' => $item->id, 'source' => InventoryMovement::MANUAL, 'direction' => $direction, 'notes' => $notes],
            ['quantity' => $quantity, 'movement_date' => $date, 'user_id' => $userId],
        );
    }
}
