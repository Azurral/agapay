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
            ['Admin_01', ['Rosa', 'L', 'Mendez'], Role::ADMIN, User::STATUS_ACTIVE, 'admin'],
            ['Agritech_02', ['Daniel', 'B', 'Fagyan'], Role::AGRITECH, User::STATUS_ACTIVE, 'agritech'],
            ['Encoder_03', ['Liza', 'C', 'Ngalob'], Role::ENCODER, User::STATUS_ACTIVE, 'encoder'],
            ['Encoder_04', ['Mark', null, 'Palangdan'], null, User::STATUS_INACTIVE, 'encoder'],
        ];

        foreach ($accounts as [$username, [$first, $middle, $last], $role, $status, $avatar]) {
            User::updateOrCreate(['username' => $username], [
                'name' => User::fullName($first, $middle, $last),   // seeders run without model events
                'first_name' => $first,
                'middle_initial' => $middle,
                'last_name' => $last,
                'password' => config('agapay.seed_password'),
                'role_id' => $role ? Role::where('slug', $role)->value('id') : null,
                'status' => $status,
                'avatar' => $avatar,
            ]);
        }
    }
}
