<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Seed data is not user activity, so it stays out of the audit trail.
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            BarangaySeeder::class,
            UserSeeder::class,
            BeneficiarySeeder::class,
        ]);
    }
}
