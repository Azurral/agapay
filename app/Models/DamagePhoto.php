<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A JPG/PNG attached to a damage report. Kept on the private disk (storage/app/private/damage), never under
 * public/storage, because the photos show named farmers' fields: they are served only to signed-in users.
 */
#[Fillable(['damage_report_id', 'path', 'original_name', 'size', 'latitude', 'longitude', 'distance_m'])]
class DamagePhoto extends Model
{
    /** The private disk the photos are kept on (agapay.files_disk): local, or object storage on the cloud copy. */
    public static function disk(): string
    {
        return (string) config('agapay.files_disk', 'local');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'damage_report_id')->withTrashed();
    }
}
