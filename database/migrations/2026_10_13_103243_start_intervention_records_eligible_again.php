<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The eligibility verification step was taken out again (the group's decision): records start eligible and can be
 * released at once; special cases (Deceased, Duplicate, Relocated, ...) are still tagged on the DA/LGU lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('intervention_records')->where('validation_status', 'pending')->update(['validation_status' => 'eligible']);

        Schema::table('intervention_records', function (Blueprint $table) {
            $table->string('validation_status', 16)->default('eligible')->change();
        });
    }

    public function down(): void
    {
        Schema::table('intervention_records', function (Blueprint $table) {
            $table->string('validation_status', 16)->default('pending')->change();
        });
    }
};
