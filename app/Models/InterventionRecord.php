<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\InterventionRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'beneficiary_id', 'intervention_id', 'distribution_cycle_id', 'quantity', 'validation_status', 'claim_status',
    'date_distributed', 'proxy_claimant', 'proof_note', 'override_reason', 'validated_by', 'claimed_by', 'created_by',
])]
class InterventionRecord extends Model
{
    /** @use HasFactory<InterventionRecordFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /** New records start eligible: eligibility is checked outside AGAPAY, special cases are set by hand. */
    public const VALIDATION_ELIGIBLE = 'eligible';

    public const VALIDATION_OFW = 'ofw';

    public const VALIDATION_BEDRIDDEN = 'bedridden';

    public const VALIDATION_DECEASED = 'deceased';

    public const VALIDATION_INACTIVE = 'inactive';

    public const VALIDATION_RELOCATED = 'relocated';

    public const VALIDATION_DUPLICATE = 'duplicate';

    /** status => chip label, in dropdown order. */
    public const VALIDATIONS = [
        self::VALIDATION_ELIGIBLE => 'Eligible',
        self::VALIDATION_OFW => 'OFW',
        self::VALIDATION_BEDRIDDEN => 'Bedridden',
        self::VALIDATION_DECEASED => 'Deceased',
        self::VALIDATION_INACTIVE => 'Inactive',
        self::VALIDATION_RELOCATED => 'Relocated',
        self::VALIDATION_DUPLICATE => 'Duplicate',
    ];

    /** Statuses that may be claimed (deceased only through a proxy). */
    public const CLAIMABLE = [self::VALIDATION_ELIGIBLE, self::VALIDATION_OFW, self::VALIDATION_BEDRIDDEN, self::VALIDATION_DECEASED];

    public const CLAIM_UNCLAIMED = 'unclaimed';

    public const CLAIM_CLAIMED = 'claimed';

    protected string $auditSubject = 'Intervention Record';

    protected array $auditIgnore = ['validated_by', 'claimed_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'date_distributed' => 'date'];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class)->withTrashed();
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(DistributionCycle::class, 'distribution_cycle_id');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopeOfSource(Builder $query, string $source): Builder
    {
        return $query->whereHas('intervention', fn (Builder $q) => $q->where('source', $source));
    }

    public static function validationLabel(string $status): string
    {
        return self::VALIDATIONS[$status] ?? '—';
    }

    /** Every known status shows green (Figma 407:323); an unknown value is flagged. */
    public static function validationTone(string $status): string
    {
        return array_key_exists($status, self::VALIDATIONS) ? 'ok' : 'bad';
    }

    public function isClaimed(): bool
    {
        return $this->claim_status === self::CLAIM_CLAIMED;
    }

    public function claimLabel(): string
    {
        return $this->isClaimed() ? 'Claimed' : 'Unclaimed';
    }

    public function claimTone(): string
    {
        return $this->isClaimed() ? 'ok' : 'bad';
    }

    /** "2 sacks", "1 sack", "5 L"; "-" when no quantity was recorded. */
    public function quantityDisplay(): string
    {
        if ($this->quantity === null) {
            return '-';
        }

        $qty = rtrim(rtrim((string) $this->quantity, '0'), '.');
        $unit = (string) $this->intervention?->unit;

        if ($unit === '') {
            return $qty;
        }

        return $qty.' '.($unit === 'L' ? $unit : Str::plural($unit, (float) $qty == 1 ? 1 : 2));
    }

    /** "DA - Certified Rice Seeds (Batch 2026-Q3)". */
    public function deLabel(): string
    {
        return "{$this->intervention->sourceLabel()} - {$this->intervention->name} (Batch {$this->cycle->code})";
    }

    public function auditRecordLabel(): string
    {
        return "{$this->beneficiary?->fullName()} - {$this->intervention?->name} ({$this->cycle?->code})";
    }
}
