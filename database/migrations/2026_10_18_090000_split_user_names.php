<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Users get separate name parts (as on the office forms); `name` stays as the joined full name. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_initial', 2)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_initial');
            $table->string('suffix', 10)->nullable()->after('last_name');
        });

        DB::table('users')->orderBy('id')->each(function (object $user) {
            $words = preg_split('/\s+/u', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY);
            $last = count($words) > 1 ? array_pop($words) : null;
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => implode(' ', $words) ?: null,
                'last_name' => $last,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_initial', 'last_name', 'suffix']);
        });
    }
};
