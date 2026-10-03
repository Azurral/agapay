<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One stock movement. Append-only: mistakes are corrected with an opposite movement. */
#[Fillable(['inventory_item_id', 'direction', 'quantity', 'movement_date', 'notes', 'source', 'intervention_record_id', 'user_id'])]
class InventoryMovement extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    public const MANUAL = 'manual';

    public const AUTO = 'auto';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'movement_date' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(InterventionRecord::class, 'intervention_record_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "−2 sacks" / "+120 sacks" (U+2212 minus, as in Figma). */
    public function signedLabel(): string
    {
        return ($this->direction === self::OUT ? '−' : '+').InventoryItem::quantity($this->quantity).' '.$this->item->pluralUnit($this->quantity);
    }

    /** "−2 sacks · Certified Rice Seeds · Auto-deducted: … · Jul 18, 2026". */
    public function line(): string
    {
        return collect([$this->signedLabel(), $this->item->name, $this->notes, $this->movement_date?->format('M j, Y')])
            ->filter(fn ($part) => filled($part))
            ->join(' · ');
    }
}
