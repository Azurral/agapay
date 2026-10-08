<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The address is entered in parts, like government forms (House/Lot No., Street, Sitio/Purok; the town is always Bontoc).
 * "address" stays as the joined parts, which households are grouped by. "Farm Location" becomes the farm area in hectares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('house_no', 100)->nullable()->after('address');
            $table->string('street', 100)->nullable()->after('house_no');
            $table->string('sitio', 100)->nullable()->after('street');
            $table->decimal('farm_area_ha', 8, 2)->nullable()->after('contact_number');
        });

        // Existing one-line addresses become the sitio/purok; "Poblacion (1.5 hectares)" gives a farm area of 1.5.
        DB::table('beneficiaries')->orderBy('id')->select(['id', 'address', 'farm_location'])->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                preg_match('/\d+(?:\.\d+)?/', (string) $row->farm_location, $area);
                DB::table('beneficiaries')->where('id', $row->id)->update([
                    'sitio' => mb_substr((string) $row->address, 0, 100),
                    'farm_area_ha' => isset($area[0]) && (float) $area[0] <= 9999.99 ? $area[0] : null,
                ]);
            }
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn('farm_location');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('farm_location')->nullable()->after('contact_number');
        });
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn(['house_no', 'street', 'sitio', 'farm_area_ha']);
        });
    }
};
