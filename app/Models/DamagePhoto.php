<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A JPG/PNG attached to a damage report; stored on the public disk, served only to signed-in users. */
#[Fillable(['damage_report_id', 'path', 'original_name', 'size'])]
class DamagePhoto extends Model
{
    public const DISK = 'public';

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'damage_report_id')->withTrashed();
    }
}
