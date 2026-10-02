<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Barangay extends Model
{
    /** The 16 barangays served by OMAG Bontoc (capstone paper §1.2). */
    public const NAMES = [
        'Alab Oriente', 'Alab Proper', 'Bayyo', 'Balili', 'Bontoc Ili', 'Can-eo', 'Caluttit', 'Dalican',
        'Gonogon', 'Guina-ang', 'Mainit', 'Maligcong', 'Poblacion', 'Samoki', 'Talubin', 'Tocucan',
    ];

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }
}
