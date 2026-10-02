<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Admin_01', 'Municipal Agriculturist', Role::ADMIN, User::STATUS_ACTIVE, 'admin'],
            ['Agritech_02', 'Agricultural Technologist', Role::AGRITECH, User::STATUS_ACTIVE, 'agritech'],
            ['Encoder_03', 'Data Encoder', Role::ENCODER, User::STATUS_ACTIVE, 'encoder'],
            ['Encoder_04', 'Data Encoder (Unassigned)', null, User::STATUS_INACTIVE, 'encoder'],
        ];

        foreach ($accounts as [$username, $name, $role, $status, $avatar]) {
            User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'password' => config('agapay.seed_password'),
                'role_id' => $role ? Role::where('slug', $role)->value('id') : null,
                'status' => $status,
                'avatar' => $avatar,
            ]);
        }
    }
}
