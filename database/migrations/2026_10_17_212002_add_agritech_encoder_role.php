<?php

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Adds the combined "Agricultural Technologist & Data Encoder" role to existing databases (other roles untouched). */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->where('slug', Role::AGRITECH_ENCODER)->exists()) {
            return;
        }
        $roleId = DB::table('roles')->insertGetId([
            'slug' => Role::AGRITECH_ENCODER, 'name' => 'Agricultural Technologist & Data Encoder',
            'short_name' => 'AgriTech & Encoder', 'greeting' => 'Agritech', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('permissions')->whereIn('slug', PermissionCatalog::defaults()[Role::AGRITECH_ENCODER])->pluck('id') as $permissionId) {
            DB::table('permission_role')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', Role::AGRITECH_ENCODER)->value('id');
        DB::table('users')->where('role_id', $roleId)->update(['role_id' => null]);
        DB::table('permission_role')->where('role_id', $roleId)->delete();
        DB::table('roles')->where('id', $roleId)->delete();
    }
};
