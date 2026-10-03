<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One uploaded spreadsheet, staged until confirmed (or discarded). */
#[Fillable(['user_id', 'original_name', 'stored_path', 'status', 'header_row', 'mapping', 'feedback', 'counts', 'imported_at'])]
class ImportBatch extends Model
{
    public const STAGED = 'staged';

    public const IMPORTED = 'imported';

    public const DISCARDED = 'discarded';

    protected function casts(): array
    {
        return ['mapping' => 'array', 'feedback' => 'array', 'counts' => 'array', 'imported_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_number');
    }

    public function isStaged(): bool
    {
        return $this->status === self::STAGED;
    }

    /** Rows that will become (or update) a profile on Confirm & Import. */
    public function importableCount(): int
    {
        return (int) collect($this->counts ?? [])->only([ImportRow::READY, ImportRow::FLAGGED, ImportRow::UPDATE])->sum();
    }

    public function auditRecordLabel(): string
    {
        return $this->original_name;
    }
}
