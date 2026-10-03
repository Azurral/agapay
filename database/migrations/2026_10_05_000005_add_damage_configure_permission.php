<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Existing databases get the new permission for the Administrator without re-seeding (which would reset role edits). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'slug' => 'damage.configure', 'label' => 'Manage disasters and crop reference values', 'group' => 'Damage Recording',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('slug', 'damage.configure')->value('id');
        $adminId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($permissionId && $adminId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $adminId]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'damage.configure')->delete();
    }
};
