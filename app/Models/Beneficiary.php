<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\HouseholdService;
use Database\Factories\BeneficiaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'first_name', 'middle_name', 'last_name', 'birthdate', 'address', 'barangay_id', 'contact_number',
    'farm_location', 'crop_type', 'rsbsa_number', 'rsbsa_status', 'rsbsa_status_reason', 'life_status',
    'encoding_issue', 'source', 'created_by', 'updated_by',
])]
class Beneficiary extends Model
{
    /** @use HasFactory<BeneficiaryFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const RSBSA_PENDING = 'pending_validation';

    public const RSBSA_VALIDATED = 'validated';

    public const RSBSA_ENDORSED = 'endorsed';

    public const RSBSA_REGISTERED = 'registered';

    public const RSBSA_RETURNED = 'returned';

    public const RSBSA_REJECTED = 'rejected';

    /** Applications OMAG is still working on ("Pending RSBSA"). */
    public const RSBSA_IN_PROGRESS = [self::RSBSA_PENDING, self::RSBSA_VALIDATED, self::RSBSA_ENDORSED];

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'excel_import';

    protected string $auditSubject = 'Beneficiary Profile';

    /** Bookkeeping changes that are not user edits. */
    protected array $auditIgnore = ['household_id', 'updated_by'];

    protected static function booted(): void
    {
        static::saving(function (Beneficiary $beneficiary) {
            if (! $beneficiary->exists || $beneficiary->isDirty(['address', 'barangay_id'])) {
                HouseholdService::assign($beneficiary);
            }
        });
    }

    protected function casts(): array
    {
        return ['birthdate' => 'date'];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fullName(): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }

    public function age(): int
    {
        return (int) $this->birthdate->diffInYears(now());
    }

    public function rsbsaDisplay(): string
    {
        return $this->rsbsa_number ?: '(pending)';
    }

    /** Chip label for an RSBSA status. */
    public static function rsbsaStatusLabel(?string $status): string
    {
        return match ($status) {
            self::RSBSA_PENDING => 'Pending Validation',
            self::RSBSA_VALIDATED => 'Validated',
            self::RSBSA_ENDORSED => 'Endorsed to DA-RFO',
            self::RSBSA_REGISTERED => 'Registered',
            self::RSBSA_RETURNED => 'Returned',
            self::RSBSA_REJECTED => 'Rejected',
            default => '—',
        };
    }

    /** Chip tone: statuses that need someone's attention are "bad". */
    public static function rsbsaStatusTone(?string $status): string
    {
        return in_array($status, [self::RSBSA_PENDING, self::RSBSA_RETURNED, self::RSBSA_REJECTED], true) ? 'bad' : 'ok';
    }

    public function auditRecordLabel(): string
    {
        return $this->fullName().' ('.($this->rsbsa_number ?: 'pending').')';
    }

    /** Members of this beneficiary's household, including the beneficiary. */
    public function householdSize(): int
    {
        return $this->household_id ? static::where('household_id', $this->household_id)->count() : 1;
    }

    /** @return Collection<int, Beneficiary> */
    public function otherHouseholdMembers(): Collection
    {
        if (! $this->household_id) {
            return collect();
        }

        return static::where('household_id', $this->household_id)->whereKeyNot($this->id)->orderBy('id')->get();
    }
}
