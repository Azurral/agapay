<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Existing databases keep permission labels from when they were seeded; bring them in line with the catalog. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PermissionCatalog::PERMISSIONS as $slug => [$label, $group]) {
            DB::table('permissions')->where('slug', $slug)->update(['label' => $label, 'group' => $group]);
        }
    }

    public function down(): void
    {
        // Labels are display text only; nothing to undo.
    }
};
