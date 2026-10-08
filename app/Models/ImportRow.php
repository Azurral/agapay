<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One data row of an uploaded spreadsheet, as read and checked at staging. */
#[Fillable(['import_batch_id', 'row_number', 'status', 'data', 'issues', 'beneficiary_id'])]
class ImportRow extends Model
{
    public const READY = 'ready';

    /** Older staged rows without an RSBSA number; new rows without one are simply "ready". */
    public const FLAGGED = 'flagged';

    public const UPDATE = 'update';

    public const DUPLICATE = 'duplicate';

    public const UNREADABLE = 'unreadable';

    public const LABELS = [
        self::READY => 'Ready', self::FLAGGED => 'Ready', self::UPDATE => 'Adds RSBSA No.',
        self::DUPLICATE => 'Duplicate', self::UNREADABLE => 'Excluded',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'issues' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return in_array($this->status, [self::READY, self::FLAGGED, self::UPDATE], true) ? 'ok' : 'bad';
    }
}
