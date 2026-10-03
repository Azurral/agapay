<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One farmer's crop damage from one disaster (spec rule 11). Loss and cost use the crop values copied at filing. */
#[Fillable([
    'disaster_id', 'beneficiary_id', 'barangay_id', 'crop_id', 'farm_location', 'crop_stage',
    'total_area_ha', 'partial_area_ha', 'yield_mt_per_ha', 'price_per_mt', 'partial_loss_factor', 'loss_mt', 'cost',
    'latitude', 'longitude', 'status', 'adjustment_note', 'reported_by', 'validated_by', 'validated_at',
    'delete_reason', 'deleted_by',
])]
class DamageReport extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const FOR_VALIDATION = 'for_validation';

    public const VALIDATED = 'validated';

    public const STATUS_LABELS = [self::FOR_VALIDATION => 'For Validation', self::VALIDATED => 'Validated'];

    public const STAGES = [
        'newly_planted' => 'Newly Planted', 'vegetative' => 'Vegetative', 'reproductive' => 'Reproductive',
        'maturing' => 'Maturing', 'harvested' => 'Harvested',
    ];

    protected string $auditSubject = 'Damage Report';

    protected function casts(): array
    {
        return [
            'total_area_ha' => 'decimal:2', 'partial_area_ha' => 'decimal:2',
            'yield_mt_per_ha' => 'decimal:2', 'price_per_mt' => 'decimal:2', 'partial_loss_factor' => 'decimal:2',
            'loss_mt' => 'decimal:2', 'cost' => 'decimal:2', 'latitude' => 'decimal:6', 'longitude' => 'decimal:6',
            'validated_at' => 'datetime',
        ];
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(Disaster::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class)->withTrashed();
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DamagePhoto::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isValidated(): bool
    {
        return $this->status === self::VALIDATED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return $this->isValidated() ? 'ok' : 'bad';
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->crop_stage] ?? $this->crop_stage;
    }

    /** "1.20 ha / 0.30 ha" (total / partial). */
    public function areaLabel(): string
    {
        return number_format((float) $this->total_area_ha, 2).' ha / '.number_format((float) $this->partial_area_ha, 2).' ha';
    }

    public function auditRecordLabel(): string
    {
        return "{$this->beneficiary?->fullName()} · {$this->crop?->name} · {$this->disaster?->name}";
    }
}
