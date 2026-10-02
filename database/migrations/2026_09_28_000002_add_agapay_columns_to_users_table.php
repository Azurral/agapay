<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->string('status', 16)->default('active')->after('role_id');
            $table->string('avatar', 16)->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['username', 'status', 'avatar', 'last_login_at']);
        });
    }
};
