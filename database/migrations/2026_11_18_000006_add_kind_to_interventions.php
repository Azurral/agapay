<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Goods or cash (panel: "must have a filter for goods or cash"); the cash-aid programs are marked as such. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->string('kind', 10)->default('goods')->after('name');
        });

        DB::table('interventions')->whereIn('name', ['Municipal Cash Subsidy', 'RFFA', 'PAFF'])->update(['kind' => 'cash']);
    }

    public function down(): void
    {
        Schema::table('interventions', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
