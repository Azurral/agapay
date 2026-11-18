<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** A scheduled distribution event (paper §1.4.1); cycles are kept in date order, so id order is date order. */
#[Fillable(['code', 'label', 'schedule_date', 'claim_end_date', 'venue', 'status'])]
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
        return ['schedule_date' => 'date', 'claim_end_date' => 'date'];
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

    /** The last claiming day: the claiming period runs from the schedule date to this (the schedule date alone if unset). */
    public function claimEnd(): ?Carbon
    {
        return $this->claim_end_date ?? $this->schedule_date;
    }

    /** Once the claiming period is over, every unclaimed record is verified and forwarded or closed. */
    public function claimingEnded(): bool
    {
        return $this->claimEnd() !== null && today()->gt($this->claimEnd());
    }

    /** @return list<string> the claiming days (Y-m-d) */
    public function claimingDays(): array
    {
        if (! $this->schedule_date) {
            return [];
        }

        return collect(CarbonPeriod::create($this->schedule_date, $this->claimEnd()))
            ->map(fn ($day) => $day->toDateString())->values()->all();
    }

    /** The next cycle by schedule date (where unclaimed records are forwarded). */
    public function next(): ?self
    {
        return static::whereNotNull('schedule_date')->where('schedule_date', '>', $this->schedule_date)
            ->orderBy('schedule_date')->first();
    }

    public function claimingLabel(): string
    {
        $start = $this->schedule_date?->format('M j, Y');
        $end = $this->claimEnd();

        return $end && $this->schedule_date && ! $end->isSameDay($this->schedule_date)
            ? "{$this->schedule_date->format('M j')} - {$end->format('M j, Y')}"
            : (string) $start;
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
