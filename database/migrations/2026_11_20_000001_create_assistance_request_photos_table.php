<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos of the damage on a crisis-related assistance request (panel: proof that the area was hit, where and when):
 * the GPS position and the moment each was taken, read from the photo itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_request_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->dateTime('taken_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_request_photos');
    }
};
