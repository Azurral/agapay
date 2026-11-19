<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The proof behind a change of a record's validation status (a signed certification or validation sheet, photo or
 * PDF), kept on the private disk with AGAPAY's automatic checks and the Administrator's decision.
 */
#[Fillable([
    'intervention_record_id', 'from_status', 'to_status', 'path', 'original_name', 'sha256', 'checks', 'status',
    'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class ValidationProof extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return ['checks' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(InterventionRecord::class, 'intervention_record_id')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function failedChecks(): int
    {
        return collect($this->checks ?? [])->where('result', 'fail')->count();
    }
}
