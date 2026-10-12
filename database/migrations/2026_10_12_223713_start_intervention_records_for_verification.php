<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eligibility is verified again (as in the paper): new records start "For Verification" until the automatic checks
 * have run and a person has confirmed them. Existing records keep the status they already have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervention_records', function (Blueprint $table) {
            $table->string('validation_status', 16)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('intervention_records', function (Blueprint $table) {
            $table->string('validation_status', 16)->default('eligible')->change();
        });
    }
};
