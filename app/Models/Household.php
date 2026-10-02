<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['barangay_id', 'address_key', 'household_no'])]
class Household extends Model
{
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }
}
