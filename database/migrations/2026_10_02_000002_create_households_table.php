<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained();
            $table->string('address_key');
            $table->string('household_no', 20)->nullable()->unique();
            $table->timestamps();
            $table->unique(['barangay_id', 'address_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
