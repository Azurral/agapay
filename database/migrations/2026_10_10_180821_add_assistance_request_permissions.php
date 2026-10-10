<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Existing databases get the assistance request permissions without re-seeding (which would reset role edits). */
return new class extends Migration
{
    /** slug => [label, roles] */
    private const PERMISSIONS = [
        'requests.create' => ['File assistance requests', ['administrator', 'agricultural_technologist', 'data_encoder']],
        'requests.decide' => ['Approve or deny assistance requests', ['administrator']],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $slug => [$label, $roles]) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug, 'label' => $label, 'group' => 'Interventions', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->whereIn('slug', $roles)->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->delete();
    }
};
