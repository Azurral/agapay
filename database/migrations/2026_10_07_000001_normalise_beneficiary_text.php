<?php

use App\Models\Beneficiary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Profiles saved before names were squished and RSBSA numbers upper-cased get the same treatment. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('beneficiaries')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $changes = [];
                foreach (Beneficiary::SQUISHED as $field) {
                    if (is_string($row->{$field}) && ($clean = Beneficiary::squish($row->{$field})) !== $row->{$field}) {
                        $changes[$field] = $clean;
                    }
                }
                if (is_string($row->rsbsa_number) && ($number = Beneficiary::normalizeRsbsa($row->rsbsa_number)) !== $row->rsbsa_number) {
                    // Two numbers that differ only by case or spaces keep their old form rather than break the unique index.
                    if (! DB::table('beneficiaries')->where('id', '!=', $row->id)->where('rsbsa_number', $number)->exists()) {
                        $changes['rsbsa_number'] = $number;
                    }
                }
                if ($changes !== []) {
                    DB::table('beneficiaries')->where('id', $row->id)->update($changes);
                }
            }
        });
    }

    public function down(): void
    {
        // Normalised text cannot be un-normalised.
    }
};
