<?php

namespace App\Services;

use App\Exceptions\InsufficientStock;
use App\Models\InterventionRecord;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Owns stock: manual movements and the automatic movements that follow claims (spec rule 7). */
final class InventoryService
{
    /** A delivery, adjustment or manual correction from the Record Stock Movement modal. */
    public function record(InventoryItem $item, string $direction, mixed $quantity, ?string $date, ?string $notes, User $actor): InventoryMovement
    {
        $data = Validator::make(compact('direction', 'quantity', 'date', 'notes'), [
            'direction' => ['required', Rule::in([InventoryMovement::IN, InventoryMovement::OUT])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999', 'decimal:0,2'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'direction.in' => 'Choose Stock In or Stock Out.',
            'quantity.gt' => 'Enter a quantity above zero.',
            'quantity.decimal' => 'Use at most 2 decimal places.',
            'date.before_or_equal' => 'The date cannot be in the future.',
        ])->validate();

        return DB::transaction(function () use ($item, $data, $actor) {
            $item = $this->lockItem($item->id);
            $quantity = round((float) $data['quantity'], 2);

            if ($data['direction'] === InventoryMovement::OUT) {
                $this->ensureAvailable($item, $quantity);
            }

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'direction' => $data['direction'],
                'quantity' => $quantity,
                'movement_date' => $data['date'],
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'source' => InventoryMovement::MANUAL,
                'user_id' => $actor->id,
            ]);

            AuditLogger::record(
                $data['direction'] === InventoryMovement::IN ? 'Recorded Stock In' : 'Recorded Stock Out',
                $item, null, [], ['quantity' => $quantity, 'date' => $data['date'], 'notes' => $movement->notes], $actor,
            );

            return $movement;
        });
    }

    /**
     * Makes a record's automatic movements match what it should have deducted: its quantity while it is
     * claimed and active and its program is stocked, otherwise nothing. Run inside the caller's transaction.
     *
     * @param  string  $reason  unclaimed | archived | quantity reduced — used in the "Returned" note
     */
    public function syncRecord(InterventionRecord $record, User $actor, string $reason = 'unclaimed'): void
    {
        $record->loadMissing(['intervention', 'beneficiary']);

        // Net auto deduction per item so far.
        $current = InventoryMovement::where(['intervention_record_id' => $record->id, 'source' => InventoryMovement::AUTO])
            ->get(['inventory_item_id', 'direction', 'quantity'])
            ->groupBy('inventory_item_id')
            ->map(fn ($moves) => round($moves->sum(fn ($m) => $m->direction === InventoryMovement::OUT ? (float) $m->quantity : -(float) $m->quantity), 2));

        // A deduction that still stands stays on the item it came from, even if the program was relinked since;
        // only a record with nothing deducted yet follows the program's current item.
        $stays = $record->isClaimed() && ! $record->trashed() && (float) $record->quantity > 0;
        $deductedFrom = $current->filter(fn (float $net) => $net > 0)->keys()->first();
        $target = $stays ? ($deductedFrom ?? $record->intervention?->inventory_item_id) : null;
        $wanted = $target ? round((float) $record->quantity, 2) : 0.0;

        // Sorted, so concurrent syncs always lock items in the same order.
        $itemIds = $current->keys()->push($target)->filter()->unique()->sort()->values();
        $who = "{$record->beneficiary->fullName()} ({$record->beneficiary->rsbsaDisplay()})";

        foreach ($itemIds as $itemId) {
            $difference = round(($itemId === $target ? $wanted : 0.0) - ($current[$itemId] ?? 0.0), 2);

            if ($difference > 0) {
                $item = $this->lockItem($itemId);
                $this->ensureAvailable($item, $difference);
                $this->autoMovement($item, $record, InventoryMovement::OUT, $difference,
                    $record->date_distributed?->toDateString() ?? today()->toDateString(), "Auto-deducted: distribution to {$who}", $actor);
            } elseif ($difference < 0) {
                $this->autoMovement($this->lockItem($itemId), $record, InventoryMovement::IN, -$difference,
                    today()->toDateString(), "Returned: {$reason} distribution to {$who}", $actor);
            }
        }
    }

    public function lowStockCount(): int
    {
        return InventoryItem::withStock()->get()->filter->isLow()->count();
    }

    /** Locks the item row so concurrent movements check the balance one at a time. */
    private function lockItem(int $id): InventoryItem
    {
        return InventoryItem::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function ensureAvailable(InventoryItem $item, float $needed): void
    {
        $balance = $item->balance();

        if ($needed > $balance) {
            throw new InsufficientStock(sprintf('Not enough stock: %s has %s %s left, %s needed.',
                $item->name, InventoryItem::quantity($balance), $item->pluralUnit($balance), InventoryItem::quantity($needed)));
        }
    }

    private function autoMovement(InventoryItem $item, InterventionRecord $record, string $direction, float $quantity, string $date, string $notes, User $actor): void
    {
        InventoryMovement::create([
            'inventory_item_id' => $item->id, 'direction' => $direction, 'quantity' => $quantity, 'movement_date' => $date,
            'notes' => Str::limit($notes, 254, '…'), 'source' => InventoryMovement::AUTO, 'intervention_record_id' => $record->id, 'user_id' => $actor->id,
        ]);
    }
}
