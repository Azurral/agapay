<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Existing databases get the new permission wording (disasters are called crises; eligibility is no longer validated). */
return new class extends Migration
{
    private const LABELS = [
        'damage.configure' => ['Manage crises and crop reference values', 'Manage disasters and crop reference values'],
        'interventions.validate' => ['Change eligibility status (e.g. Deceased, Duplicate)', 'Validate beneficiary eligibility'],
    ];

    public function up(): void
    {
        foreach (self::LABELS as $slug => [$new]) {
            DB::table('permissions')->where('slug', $slug)->update(['label' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::LABELS as $slug => [, $old]) {
            DB::table('permissions')->where('slug', $slug)->update(['label' => $old]);
        }
    }
};
