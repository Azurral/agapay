<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Colour theme and dark mode, saved on the account so they follow the user to any office laptop (null = the defaults).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('color_theme', 10)->nullable()->after('sidebar_open');
            $table->boolean('dark_mode')->nullable()->after('color_theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['color_theme', 'dark_mode']);
        });
    }
};
