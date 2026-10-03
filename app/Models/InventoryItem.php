<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A stocked good. Its balance is always computed from its movements (stock-in minus stock-out). */
#[Fillable(['name', 'unit', 'unit_label', 'low_stock_threshold'])]
class InventoryItem extends Model
{
    use Auditable;

    protected string $auditSubject = 'Inventory Item';

    protected function casts(): array
    {
        return ['low_stock_threshold' => 'decimal:2'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    /** Eager-loads stock_in_sum_quantity and stock_out_sum_quantity. */
    public function scopeWithStock(Builder $query): Builder
    {
        return $query->withSum(['movements as stock_in' => fn (Builder $q) => $q->where('direction', InventoryMovement::IN)], 'quantity')
            ->withSum(['movements as stock_out' => fn (Builder $q) => $q->where('direction', InventoryMovement::OUT)], 'quantity');
    }

    public function stockIn(): float
    {
        return $this->stockSum('stock_in_sum_quantity', InventoryMovement::IN);
    }

    public function stockOut(): float
    {
        return $this->stockSum('stock_out_sum_quantity', InventoryMovement::OUT);
    }

    public function balance(): float
    {
        return round($this->stockIn() - $this->stockOut(), 2);
    }

    /** A threshold of 0 means "never warn". */
    public function isLow(): bool
    {
        return (float) $this->low_stock_threshold > 0 && $this->balance() <= (float) $this->low_stock_threshold;
    }

    /** "2", "2.5", "120" — no trailing zeros. */
    public static function quantity(float|string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.');
    }

    /** "1 sack", "2 sacks". */
    public function pluralUnit(float|string $quantity): string
    {
        return Str::plural($this->unit, (float) $quantity == 1 ? 1 : 2);
    }

    public function auditRecordLabel(): string
    {
        return $this->name;
    }

    private function stockSum(string $attribute, string $direction): float
    {
        if (array_key_exists($attribute, $this->attributes)) {
            return round((float) $this->attributes[$attribute], 2);
        }

        return round((float) $this->movements()->where('direction', $direction)->sum('quantity'), 2);
    }
}
