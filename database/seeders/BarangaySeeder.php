<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Barangay::NAMES as $name) {
            Barangay::firstOrCreate(['name' => $name]);
        }
    }
}
