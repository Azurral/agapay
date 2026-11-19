<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of identity (panel): the RSBSA / agricultural ID with the farmer's signature beside the valid ID, a
 * fingerprint (SHA-256) of each ID photo so one photo cannot be reused for another farmer, and the results of the
 * automatic checks the Administrator sees before approving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('rsbsa_id_photo')->nullable()->after('valid_id_photo');
            $table->char('valid_id_sha256', 64)->nullable()->index()->after('rsbsa_id_photo');
            $table->char('rsbsa_id_sha256', 64)->nullable()->index()->after('valid_id_sha256');
            $table->json('id_checks')->nullable()->after('rsbsa_id_sha256');
            // The ID number is encrypted, so a keyed fingerprint of "type + number" finds the same ID on two farmers.
            $table->char('valid_id_key', 64)->nullable()->index()->after('valid_id_number');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropIndex(['valid_id_sha256']);
            $table->dropIndex(['rsbsa_id_sha256']);
            $table->dropIndex(['valid_id_key']);
            $table->dropColumn(['rsbsa_id_photo', 'valid_id_sha256', 'rsbsa_id_sha256', 'id_checks', 'valid_id_key']);
        });
    }
};
