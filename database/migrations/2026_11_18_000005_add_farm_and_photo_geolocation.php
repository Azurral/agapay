<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Geolocation (panel): each farm's GPS position, and where each damage photo was taken (read from its EXIF). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->decimal('farm_latitude', 9, 6)->nullable()->after('farm_area_ha');
            $table->decimal('farm_longitude', 9, 6)->nullable()->after('farm_latitude');
        });

        Schema::table('damage_photos', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable()->after('size');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
            $table->unsignedInteger('distance_m')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('damage_photos', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude', 'distance_m']));
        Schema::table('beneficiaries', fn (Blueprint $table) => $table->dropColumn(['farm_latitude', 'farm_longitude']));
    }
};
