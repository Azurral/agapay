<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('intervention_id')->constrained()->restrictOnDelete();
            $table->foreignId('distribution_cycle_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('validation_status', 16)->default('pending')->index();
            $table->string('claim_status', 16)->default('unclaimed')->index();
            $table->date('date_distributed')->nullable();
            $table->string('proxy_claimant')->nullable();
            $table->text('proof_note')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delete_reason')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['beneficiary_id', 'intervention_id', 'distribution_cycle_id'], 'records_slot_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_records');
    }
};
