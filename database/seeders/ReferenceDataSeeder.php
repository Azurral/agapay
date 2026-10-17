<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\Intervention;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * What a real installation starts with (php artisan agapay:install): roles and permissions, Bontoc's barangays,
 * the common DA and LGU programs, and crop reference values for damage computation. No sample people or records.
 */
class ReferenceDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, BarangaySeeder::class]);

        foreach (InterventionSeeder::PROGRAMS as [$source, $name, $unit, $onePerHousehold, $allowRepeat]) {
            Intervention::firstOrCreate(['source' => $source, 'name' => $name], [
                'unit' => $unit, 'one_per_household' => $onePerHousehold, 'allow_repeat' => $allowRepeat, 'is_active' => true,
            ]);
        }

        // Starting values only: the Administrator reviews them under Crisis Reports, Crop Values.
        foreach (DamageSeeder::CROPS as $name => [$yield, $price]) {
            Crop::firstOrCreate(['name' => $name], ['yield_mt_per_ha' => $yield, 'price_per_mt' => $price, 'partial_loss_factor' => 0.50]);
        }
    }
}
