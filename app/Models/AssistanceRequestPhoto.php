<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo of the damage attached to a crisis-related assistance request, with where (GPS) and when it was taken. Kept
 * on the same private disk as the damage report photos, served only to signed-in users.
 */
#[Fillable(['assistance_request_id', 'path', 'original_name', 'size', 'latitude', 'longitude', 'distance_m', 'taken_at'])]
class AssistanceRequestPhoto extends Model
{
    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'distance_m' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AssistanceRequest::class, 'assistance_request_id');
    }
}
