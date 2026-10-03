<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name'])]
class Barangay extends Model
{
    /** The 16 barangays served by OMAG Bontoc (capstone paper §1.2). */
    public const NAMES = [
        'Alab Oriente', 'Alab Proper', 'Bayyo', 'Balili', 'Bontoc Ili', 'Can-eo', 'Caluttit', 'Dalican',
        'Gonogon', 'Guina-ang', 'Mainit', 'Maligcong', 'Poblacion', 'Samoki', 'Talubin', 'Tocucan',
    ];

    private const OPTIONS_CACHE = 'barangay-options';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::OPTIONS_CACHE));
        static::deleted(fn () => Cache::forget(self::OPTIONS_CACHE));
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    /**
     * id => name, by name; cached because the search bar on every page needs it.
     *
     * @return Collection<int, string>
     */
    public static function options(): Collection
    {
        return collect(Cache::rememberForever(self::OPTIONS_CACHE, fn () => static::orderBy('name')->pluck('name', 'id')->all()));
    }
}
