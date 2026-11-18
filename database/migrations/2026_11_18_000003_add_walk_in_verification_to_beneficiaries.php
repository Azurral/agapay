<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * No ghost farmers: each walk-in registration carries a valid ID (type, encrypted number, photo) and waits for the
 * Administrator's approval. Profiles already in AGAPAY (imported from the DA masterlist or approved by the office
 * before this change) count as verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('valid_id_type', 30)->nullable()->after('rsbsa_status_reason');
            $table->text('valid_id_number')->nullable()->after('valid_id_type');
            $table->string('valid_id_photo')->nullable()->after('valid_id_number');
            $table->string('verification_status', 16)->default('verified')->after('valid_id_photo');
            $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->string('rejection_reason', 500)->nullable()->after('verified_at');
            $table->index('verification_status');
        });

        DB::table('permissions')->insertOrIgnore([
            'slug' => 'beneficiaries.approve', 'label' => 'Approve walk-in registrations', 'group' => 'Beneficiaries',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('slug', 'beneficiaries.approve')->value('id');
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'beneficiaries.approve')->delete();
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['valid_id_type', 'valid_id_number', 'valid_id_photo', 'verification_status', 'verified_at', 'rejection_reason']);
        });
    }
};
