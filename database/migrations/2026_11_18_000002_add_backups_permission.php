<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Existing databases: the Administrator gets "Back up and download records" without re-seeding (which would reset role edits). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'slug' => 'backups.manage', 'label' => 'Back up and download records', 'group' => 'Administration',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('slug', 'backups.manage')->value('id');
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'backups.manage')->delete();
    }
};
