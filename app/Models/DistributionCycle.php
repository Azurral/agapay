<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A scheduled distribution event (paper §1.4.1); cycles are kept in date order, so id order is date order. */
#[Fillable(['code', 'label', 'schedule_date', 'venue', 'status'])]
class DistributionCycle extends Model
{
    use Auditable;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [self::STATUS_SCHEDULED => 'Scheduled', self::STATUS_ONGOING => 'Ongoing', self::STATUS_COMPLETED => 'Completed'];

    protected string $auditSubject = 'Distribution Cycle';

    protected function casts(): array
    {
        return ['schedule_date' => 'date'];
    }

    public function records(): HasMany
    {
        return $this->hasMany(InterventionRecord::class);
    }

    /** The ongoing cycle, else the latest scheduled one, else the latest one, by schedule date. */
    public static function current(): ?self
    {
        $latest = fn ($query) => $query->orderByDesc('schedule_date')->orderByDesc('code')->first();

        return $latest(static::where('status', self::STATUS_ONGOING))
            ?? $latest(static::where('status', self::STATUS_SCHEDULED))
            ?? $latest(static::query());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function auditRecordLabel(): string
    {
        return $this->label;
    }
}
