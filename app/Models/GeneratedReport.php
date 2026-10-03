<?php

namespace App\Models;

use App\Support\ReportCriteria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report file generated from /reports, kept so it can be downloaded again exactly as issued (spec §4). */
#[Fillable(['user_id', 'distribution_cycle_id', 'program', 'start_date', 'end_date', 'format', 'file_name', 'path', 'rows'])]
class GeneratedReport extends Model
{
    public const DISK = 'local';

    protected function casts(): array
    {
        return ['start_date' => 'immutable_date', 'end_date' => 'immutable_date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(DistributionCycle::class, 'distribution_cycle_id');
    }

    /** The criteria the file was generated with (for its labels). */
    public function criteria(): ReportCriteria
    {
        return new ReportCriteria($this->cycle, $this->program, $this->start_date, $this->end_date, $this->format);
    }
}
