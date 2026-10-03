<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Crop reference values for the damage formula (spec rule 11); edited by the Administrator. */
#[Fillable(['name', 'yield_mt_per_ha', 'price_per_mt', 'partial_loss_factor'])]
class Crop extends Model
{
    protected function casts(): array
    {
        return ['yield_mt_per_ha' => 'decimal:2', 'price_per_mt' => 'decimal:2', 'partial_loss_factor' => 'decimal:2'];
    }
}
