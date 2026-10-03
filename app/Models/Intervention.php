<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source', 'name', 'unit', 'inventory_item_id', 'one_per_household', 'allow_repeat', 'is_active'])]
class Intervention extends Model
{
    public const SOURCE_DA = 'da';

    public const SOURCE_LGU = 'lgu';

    public const SOURCES = [self::SOURCE_DA, self::SOURCE_LGU];

    protected function casts(): array
    {
        return ['one_per_household' => 'boolean', 'allow_repeat' => 'boolean', 'is_active' => 'boolean'];
    }

    public function records(): HasMany
    {
        return $this->hasMany(InterventionRecord::class);
    }

    /** The stocked good this program hands out (null for cash aid). */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** "DA - Complete Fertilizer". */
    public function sourcedName(): string
    {
        return "{$this->sourceLabel()} - {$this->name}";
    }

    /** "DA" or "LGU". */
    public function sourceLabel(): string
    {
        return strtoupper($this->source);
    }
}
