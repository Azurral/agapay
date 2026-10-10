<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\AssistanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A farmer asking OMAG for a program; the Administrator approves it into a program record, or denies it with a reason. */
#[Fillable([
    'beneficiary_id', 'intervention_id', 'disaster_id', 'crop_id', 'quantity', 'reason',
    'status', 'decision_note', 'decided_by', 'decided_at', 'intervention_record_id', 'created_by',
])]
class AssistanceRequest extends Model
{
    /** @use HasFactory<AssistanceRequestFactory> */
    use Auditable, HasFactory;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DENIED = 'denied';

    public const STATUSES = [self::PENDING => 'Pending', self::APPROVED => 'Approved', self::DENIED => 'Denied'];

    protected string $auditSubject = 'Assistance Request';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class)->withTrashed();
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(Disaster::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    /** The program record an approval created. */
    public function record(): BelongsTo
    {
        return $this->belongsTo(InterventionRecord::class, 'intervention_record_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** "Released" once the approved program was actually given out. */
    public function isReleased(): bool
    {
        return $this->status === self::APPROVED && $this->record?->isClaimed();
    }

    public function statusLabel(): string
    {
        return $this->isReleased() ? 'Released' : (self::STATUSES[$this->status] ?? $this->status);
    }

    /** Chip tone: decided in the farmer's favour is green, denied red, pending grey. */
    public function statusTone(): string
    {
        return match ($this->status) {
            self::APPROVED => 'ok',
            self::DENIED => 'bad',
            default => 'neutral',
        };
    }

    public function auditRecordLabel(): string
    {
        return $this->beneficiary?->fullName().' - '.$this->intervention?->name;
    }
}
