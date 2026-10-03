<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('distribution_cycle_id')->constrained();
            $table->string('program', 3);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('format', 4);
            $table->string('file_name');
            $table->string('path');
            $table->unsignedInteger('rows');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
