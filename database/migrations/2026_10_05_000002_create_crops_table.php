<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->decimal('yield_mt_per_ha', 8, 2);
            $table->decimal('price_per_mt', 12, 2);
            $table->decimal('partial_loss_factor', 4, 2)->default(0.50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crops');
    }
};
