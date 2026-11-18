<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A scanned acknowledgment sheet with the farmers' wet signatures: proof of one program's release in one cycle. */
#[Fillable(['distribution_cycle_id', 'intervention_id', 'path', 'original_name', 'uploaded_by'])]
class SignedSheet extends Model
{
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(DistributionCycle::class, 'distribution_cycle_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
