<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disaster_id')->constrained();
            $table->foreignId('beneficiary_id')->constrained();
            $table->foreignId('barangay_id')->constrained();
            $table->foreignId('crop_id')->constrained();
            $table->string('farm_location')->nullable();
            $table->string('crop_stage', 20);
            $table->decimal('total_area_ha', 8, 2)->default(0);
            $table->decimal('partial_area_ha', 8, 2)->default(0);
            // Crop values at filing time, so later edits to the crop never change this report's figures.
            $table->decimal('yield_mt_per_ha', 8, 2);
            $table->decimal('price_per_mt', 12, 2);
            $table->decimal('partial_loss_factor', 4, 2);
            $table->decimal('loss_mt', 10, 2);
            $table->decimal('cost', 14, 2);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('status', 20)->default('for_validation');
            $table->text('adjustment_note')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->timestamp('validated_at')->nullable();
            $table->softDeletes();
            $table->string('delete_reason')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['disaster_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};
