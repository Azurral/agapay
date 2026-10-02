<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::PERMISSIONS as $slug => [$label, $group]) {
            Permission::updateOrCreate(['slug' => $slug], ['label' => $label, 'group' => $group]);
        }

        $roles = [
            Role::ADMIN => ['Administrator', 'Administrator', 'Admin'],
            Role::AGRITECH => ['Agricultural Technologist', 'Agricultural Tech', 'Agritech'],
            Role::ENCODER => ['Data Encoder', 'Data Encoder', 'Encoder'],
        ];

        foreach ($roles as $slug => [$name, $short, $greeting]) {
            $role = Role::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'short_name' => $short, 'greeting' => $greeting,
            ]);

            $role->permissions()->sync(
                Permission::whereIn('slug', PermissionCatalog::defaults()[$slug])->pluck('id')
            );
        }
    }
}
