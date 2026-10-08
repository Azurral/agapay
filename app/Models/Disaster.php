<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A typhoon, flood or other event that damage reports are filed under. */
#[Fillable(['name', 'occurred_on'])]
class Disaster extends Model
{
    use Auditable;

    protected string $auditSubject = 'Crisis';

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    /** The default filter of the damage list: the most recent event. */
    public static function latestEvent(): ?self
    {
        return static::orderByDesc('occurred_on')->orderByDesc('id')->first();
    }

    public function auditRecordLabel(): string
    {
        return "{$this->name} ({$this->occurred_on->format('M j, Y')})";
    }
}
