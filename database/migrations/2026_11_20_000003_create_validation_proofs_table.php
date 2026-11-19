<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of eligibility (panel): each change of a record's validation status (Eligible, OFW, Bedridden, Deceased,
 * Inactive, Relocated, Duplicate) carries a proof file. An Agricultural Technologist's change waits here until the
 * Administrator approves or rejects it; the Administrator's own change is approved at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validation_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_record_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('path');
            $table->string('original_name');
            $table->char('sha256', 64)->index();
            $table->json('checks')->nullable();
            $table->string('status', 10)->default('pending')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_proofs');
    }
};
