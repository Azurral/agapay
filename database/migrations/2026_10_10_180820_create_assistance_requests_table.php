<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A farmer asking OMAG for a program (seeds, fertilizer, cash aid...), often after a crisis; the Administrator decides. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained();
            $table->foreignId('intervention_id')->constrained();
            $table->foreignId('disaster_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crop_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('intervention_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_requests');
    }
};
