<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claiming calendar (panel): each cycle has a claiming period; once it is over, every unclaimed record is verified and
 * either forwarded to the next cycle's claiming date or closed with a reason. Signed acknowledgment sheets (wet
 * signatures) are kept as proof of each release.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_cycles', function (Blueprint $table) {
            $table->date('claim_end_date')->nullable()->after('schedule_date');
        });

        Schema::table('intervention_records', function (Blueprint $table) {
            $table->string('forward_status', 16)->nullable()->after('claim_status');
            $table->foreignId('forwarded_record_id')->nullable()->after('forward_status')->constrained('intervention_records')->nullOnDelete();
            $table->string('forward_note', 500)->nullable()->after('forwarded_record_id');
            $table->foreignId('forwarded_by')->nullable()->after('forward_note')->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable()->after('forwarded_by');
        });

        Schema::create('signed_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_sheets');
        Schema::table('intervention_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forwarded_record_id');
            $table->dropConstrainedForeignId('forwarded_by');
            $table->dropColumn(['forward_status', 'forward_note', 'forwarded_at']);
        });
        Schema::table('distribution_cycles', function (Blueprint $table) {
            $table->dropColumn('claim_end_date');
        });
    }
};
