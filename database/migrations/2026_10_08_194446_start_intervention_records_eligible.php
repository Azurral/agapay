<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eligibility is no longer checked in AGAPAY: records start eligible and can be released right away.
 * Special cases (Deceased, Duplicate, Relocated, ...) are still set by hand.
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
