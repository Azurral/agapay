<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\HouseholdService;
use Database\Factories\BeneficiaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'first_name', 'middle_name', 'last_name', 'birthdate', 'address', 'house_no', 'street', 'sitio', 'barangay_id', 'contact_number',
    'farm_area_ha', 'crop_type', 'rsbsa_number', 'rsbsa_status', 'rsbsa_status_reason', 'life_status',
    'encoding_issue', 'source', 'created_by', 'updated_by',
])]
class Beneficiary extends Model
{
    /** @use HasFactory<BeneficiaryFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /** Every profile is registered on entry (OMAG no longer tracks an RSBSA application workflow). */
    public const RSBSA_REGISTERED = 'registered';

    /** Shown instead of an RSBSA number a farmer does not have (LGU programs do not need one). */
    public const NO_RSBSA = 'N/A';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'excel_import';

    protected string $auditSubject = 'Beneficiary Profile';

    /** Bookkeeping changes that are not user edits. */
    protected array $auditIgnore = ['household_id', 'updated_by'];

    /** Every farmer lives in Bontoc: the barangay list is the town's, so these parts of the address are fixed. */
    public const MUNICIPALITY = 'Bontoc';

    public const PROVINCE = 'Mountain Province';

    public const REGION = 'Cordillera Administrative Region (CAR)';

    /** Address parts, in government-form order; "address" stores them joined. */
    public const ADDRESS_PARTS = ['house_no', 'street', 'sitio'];

    /** Free-text fields stored trimmed with single spaces, so typed and imported data match alike. */
    public const SQUISHED = ['first_name', 'middle_name', 'last_name', 'address', 'house_no', 'street', 'sitio', 'crop_type'];

    protected static function booted(): void
    {
        static::saving(function (Beneficiary $beneficiary) {
            foreach (self::SQUISHED as $field) {
                if (is_string($beneficiary->{$field})) {
                    $beneficiary->{$field} = self::squish($beneficiary->{$field});
                }
            }
            // The parts are the source; a one-line address given on its own (older code, tests) becomes the sitio.
            if ($beneficiary->isDirty(self::ADDRESS_PARTS) || ! $beneficiary->isDirty('address')) {
                $beneficiary->address = self::joinAddress($beneficiary->only(self::ADDRESS_PARTS)) ?? $beneficiary->address;
            } else {
                $beneficiary->fill(['house_no' => null, 'street' => null, 'sitio' => $beneficiary->address]);
            }
            if ($beneficiary->isDirty('rsbsa_number')) {
                $beneficiary->rsbsa_number = self::normalizeRsbsa($beneficiary->rsbsa_number);
            }

            if (! $beneficiary->exists || $beneficiary->isDirty(['address', 'barangay_id'])) {
                HouseholdService::assign($beneficiary);
            }
        });

        // A household nobody belongs to any more (archived members included) is removed after a move.
        static::saved(function (Beneficiary $beneficiary) {
            $old = $beneficiary->getOriginal('household_id');
            if ($beneficiary->wasChanged('household_id') && $old && ! static::withTrashed()->where('household_id', $old)->exists()) {
                Household::whereKey($old)->delete();
            }
        });
    }

    /** "  Dela\t Cruz " → "Dela Cruz"; blank → null. */
    public static function squish(?string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    /**
     * "12, Rizal St., Purok 3"; null when every part is blank.
     *
     * @param  array<string, string|null>  $parts
     */
    public static function joinAddress(array $parts): ?string
    {
        $joined = collect($parts)->map(fn ($part) => self::squish($part))->filter()->implode(', ');

        return $joined === '' ? null : $joined;
    }

    /** "12, Rizal St., Purok 3, Barangay Poblacion, Bontoc, Mountain Province". */
    public function fullAddress(): string
    {
        $barangay = $this->barangay?->name;
        $local = (string) $this->address;
        // Older imports stored "Barangay Poblacion" as the address: do not repeat it.
        if ($barangay && ! str_contains(mb_strtolower($local), mb_strtolower("Barangay {$barangay}"))) {
            $local = ltrim("{$local}, Barangay {$barangay}", ', ');
        }

        return "{$local}, ".self::MUNICIPALITY.', '.self::PROVINCE;
    }

    /** "1.5 ha", or null when not recorded. */
    public function farmAreaDisplay(): ?string
    {
        return $this->farm_area_ha === null ? null : rtrim(rtrim(number_format((float) $this->farm_area_ha, 2), '0'), '.').' ha';
    }

    /** Whether a unique-key violation is on the RSBSA number (other unique keys are not reported as an RSBSA clash). */
    public static function isRsbsaClash(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'rsbsa_number');
    }

    /** What people type for "no RSBSA number" (N/A is also AGAPAY's own display text). */
    public const NO_RSBSA_PLACEHOLDERS = ['n/a', 'na', 'n.a.', 'none', 'wala', '-', '--', '(pending)', 'pending'];

    public static function isNoRsbsa(?string $number): bool
    {
        $number = self::squish($number);

        return $number === null || in_array(mb_strtolower($number), self::NO_RSBSA_PLACEHOLDERS, true);
    }

    /** RSBSA numbers are stored trimmed and in upper case ("rsbsa-0777" → "RSBSA-0777"); placeholders like "N/A" are no number. */
    public static function normalizeRsbsa(?string $number): ?string
    {
        return self::isNoRsbsa($number) ? null : mb_strtoupper(self::squish($number));
    }

    /** "Poblacion (1.5 hectares)" → "1.5"; text without a number of hectares ("Sitio Ili 2", "500 sqm") → null. */
    public static function hectaresIn(?string $text): ?string
    {
        if (! preg_match('/(\d+(?:\.\d+)?)\s*(?:ha|has|hectares?)\b/i', (string) $text, $match) || (float) $match[1] > 9999.99) {
            return null;
        }

        return $match[1];
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

    public function interventionRecords(): HasMany
    {
        return $this->hasMany(InterventionRecord::class);
    }

    /** Crisis (damage) reports filed for this farmer; archived reports are left out by default. */
    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    /** The newest active record (by cycle, then id). */
    public function latestRecord(): HasOne
    {
        return $this->hasOne(InterventionRecord::class)->ofMany(['distribution_cycle_id' => 'max', 'id' => 'max']);
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
        return $this->rsbsa_number ?: self::NO_RSBSA;
    }

    /** Eager loads for <x-beneficiary.table>: barangay name and household size without N+1 queries. */
    public function scopeForTable(Builder $query): Builder
    {
        return $query->with(['barangay:id,name', 'household' => fn ($q) => $q->withCount('members'), 'latestRecord.intervention']);
    }

    /** Free-text search on name, full name, RSBSA number or barangay; LIKE wildcards in the term are literal. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // "Dela Cruz,Juan" and "Dela Cruz ,  Juan" both become "dela cruz, juan".
        $term = trim(preg_replace(['/\s+/', '/\s*,\s*/'], [' ', ', '], $term));
        if ($term === '') {
            return $query;
        }

        // "!" is the LIKE escape character: a backslash escape behaves differently on MySQL and SQLite.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        $join = DB::getDriverName() === 'sqlite'
            ? fn (string ...$parts) => implode(' || ', $parts)
            : fn (string ...$parts) => 'CONCAT('.implode(', ', $parts).')';
        // Names people type: "First Last", "First Middle Last", "Last First", "Last, First".
        $names = [
            $join('first_name', "' '", 'last_name'),
            $join('first_name', "' '", 'COALESCE('.$join('middle_name', "' '").", '')", 'last_name'),
            $join('last_name', "' '", 'first_name'),
            $join('last_name', "', '", 'first_name'),
        ];
        $matches = fn (string $column) => ["LOWER({$column}) LIKE ? ESCAPE '!'", [$like]];

        return $query->where(function (Builder $q) use ($matches, $names) {
            $q->whereRaw(...$matches('first_name'))
                ->orWhereRaw(...$matches('last_name'))
                ->orWhereRaw(...$matches('rsbsa_number'))
                ->orWhereHas('barangay', fn (Builder $b) => $b->whereRaw(...$matches('name')));
            foreach ($names as $name) {
                $q->orWhereRaw(...$matches($name));
            }
        });
    }

    /** Same person = same first and last name (any case), birthdate and barangay. */
    public static function isAlreadyRegistered(string $firstName, string $lastName, string $birthdate, int $barangayId, ?int $exceptId = null): bool
    {
        return static::query()
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)])
            ->whereDate('birthdate', $birthdate)
            ->where('barangay_id', $barangayId)
            ->exists();
    }

    public function auditRecordLabel(): string
    {
        return $this->fullName().' ('.$this->rsbsaDisplay().')';
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
