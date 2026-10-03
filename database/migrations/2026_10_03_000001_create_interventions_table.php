<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->string('source', 3);
            $table->string('name');
            $table->string('unit', 20)->nullable();
            $table->boolean('one_per_household')->default(false);
            $table->boolean('allow_repeat')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['source', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
