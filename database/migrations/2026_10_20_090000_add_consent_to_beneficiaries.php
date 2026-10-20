<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Data Privacy Act (RA 10173): who confirmed the farmer's consent, and when. Older profiles stay "not recorded". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->timestamp('consent_confirmed_at')->nullable()->after('source');
            $table->foreignId('consent_confirmed_by')->nullable()->after('consent_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consent_confirmed_by');
            $table->dropColumn('consent_confirmed_at');
        });
    }
};
