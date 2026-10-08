<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OMAG no longer tracks RSBSA applications (validate → endorse → number) in AGAPAY:
 * every profile is registered, and a missing number simply shows as N/A.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Workflow notes (a returned application's reason was also its encoding issue) leave the encoding queue.
        DB::table('beneficiaries')->whereColumn('encoding_issue', 'rsbsa_status_reason')->update(['encoding_issue' => null]);
        DB::table('beneficiaries')->where('encoding_issue', 'Missing RSBSA Number')->update(['encoding_issue' => null]);
        DB::table('beneficiaries')->update(['rsbsa_status' => 'registered', 'rsbsa_status_reason' => null]);

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('rsbsa_status', 32)->default('registered')->change();
        });

        DB::table('permission_role')
            ->whereIn('permission_id', DB::table('permissions')->where('slug', 'rsbsa.process')->select('id'))
            ->delete();
        DB::table('permissions')->where('slug', 'rsbsa.process')->delete();
        DB::table('permissions')->where('slug', 'rsbsa.register')->update(['label' => 'Add beneficiaries']);
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('rsbsa_status', 32)->default('pending_validation')->change();
        });

        DB::table('permissions')->insertOrIgnore([
            'slug' => 'rsbsa.process', 'label' => 'Validate and endorse RSBSA registrations', 'group' => 'Beneficiaries',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('permissions')->where('slug', 'rsbsa.register')->update(['label' => 'Encode RSBSA registrations']);
    }
};
