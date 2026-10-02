<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('birthdate');
            $table->string('address');
            $table->foreignId('barangay_id')->constrained();
            $table->string('contact_number', 20)->nullable();
            $table->string('farm_location')->nullable();
            $table->string('crop_type')->nullable();
            $table->string('rsbsa_number', 50)->nullable()->unique();
            $table->string('rsbsa_status', 32)->default('pending_validation')->index();
            $table->string('rsbsa_status_reason')->nullable();
            $table->string('life_status', 16)->default('active');
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('encoding_issue')->nullable();
            $table->string('source', 16)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delete_reason')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
