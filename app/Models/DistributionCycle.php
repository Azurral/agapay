<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'label', 'schedule_date', 'venue', 'status'])]
class DistributionCycle extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return ['schedule_date' => 'date'];
    }

    /** The ongoing cycle, else the latest scheduled one, else the latest by code. */
    public static function current(): ?self
    {
        return static::where('status', self::STATUS_ONGOING)->orderByDesc('code')->first()
            ?? static::where('status', self::STATUS_SCHEDULED)->orderByDesc('code')->first()
            ?? static::orderByDesc('code')->first();
    }
}
