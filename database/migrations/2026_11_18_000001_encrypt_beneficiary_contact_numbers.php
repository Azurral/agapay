<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Contact numbers are kept encrypted (Beneficiary casts): widen the column and encrypt the numbers already saved. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->text('contact_number')->nullable()->change();
        });

        DB::table('beneficiaries')->whereNotNull('contact_number')->orderBy('id')->lazyById()->each(function ($row) {
            if (! $this->isEncrypted($row->contact_number)) {
                DB::table('beneficiaries')->where('id', $row->id)->update(['contact_number' => Crypt::encryptString($row->contact_number)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('beneficiaries')->whereNotNull('contact_number')->orderBy('id')->lazyById()->each(function ($row) {
            if ($this->isEncrypted($row->contact_number)) {
                DB::table('beneficiaries')->where('id', $row->id)->update(['contact_number' => Crypt::decryptString($row->contact_number)]);
            }
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('contact_number', 20)->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
