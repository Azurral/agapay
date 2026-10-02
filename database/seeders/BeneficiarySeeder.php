<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** The sample people shown in the Figma wireframes, so demo screens match the designs. */
class BeneficiarySeeder extends Seeder
{
    public function run(): void
    {
        $encoder = User::where('username', 'Encoder_03')->value('id');
        $admin = User::where('username', 'Admin_01')->value('id');

        // [first, middle, last, age, address, barangay, rsbsa number, status, extra attributes]
        $people = [
            ['Juan', null, 'Dela Cruz', 45, 'Purok 3', 'Poblacion', 'RSBSA-0231', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Rice']],
            ['Maria', null, 'Dela Cruz', 43, 'Purok 3', 'Poblacion', 'RSBSA-0232', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Cabbage']],
            ['Ana', null, 'Dela Cruz', 21, 'Purok 3', 'Poblacion', 'RSBSA-0233', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Sweet Potato']],
            ['Maria', null, 'Santos', 52, 'Purok 1', 'Samoki', 'RSBSA-0198', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Rice']],
            ['Pedro', null, 'Reyes', 38, 'Sitio Ili 2', 'Bontoc Ili', null, Beneficiary::RSBSA_PENDING, ['crop_type' => 'Corn']],
            ['Lorna', null, 'Reyes', 36, 'Sitio Ili 2', 'Bontoc Ili', 'RSBSA-0145', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Corn']],
            ['Rosa', null, 'Mendez', 60, 'Purok 4', 'Samoki', 'RSBSA-0187', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Cabbage']],
            ['Carlos', null, 'Ibanez', 47, 'Sitio Ili 5', 'Bontoc Ili', 'RSBSA-0099', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Rice']],
            ['Teresa', null, 'Ibanez', 44, 'Sitio Ili 5', 'Bontoc Ili', 'RSBSA-0100', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Rice']],
            ['Liza', null, 'Domingo', 33, 'Purok 6', 'Poblacion', 'RSBSA-0304', Beneficiary::RSBSA_REGISTERED, ['crop_type' => 'Sweet Potato']],
            ['Ana', null, 'Gomez', 29, 'Purok 2', 'Poblacion', null, Beneficiary::RSBSA_PENDING, ['crop_type' => 'Cabbage']],
            ['Federico', null, 'Wasing', 56, 'Sitio Bayyo', 'Bayyo', null, Beneficiary::RSBSA_ENDORSED, [
                'source' => Beneficiary::SOURCE_IMPORT, 'encoding_issue' => 'Missing RSBSA Number', 'created_at' => '2026-07-20 09:00:00',
            ]],
            ['Estrella', null, 'Domogen', 41, 'Sitio Maligcong', 'Maligcong', null, Beneficiary::RSBSA_RETURNED, [
                'encoding_issue' => 'Awaiting Barangay Confirmation', 'rsbsa_status_reason' => 'Awaiting Barangay Confirmation', 'created_at' => '2026-07-19 14:30:00',
            ]],
        ];

        foreach ($people as $i => [$first, $middle, $last, $age, $address, $barangay, $number, $status, $extra]) {
            $createdAt = $extra['created_at'] ?? null;
            unset($extra['created_at']);
            $barangayId = Barangay::where('name', $barangay)->valueOrFail('id');

            // Keyed on name + barangay (not birthdate) because ages are kept relative to today.
            $beneficiary = Beneficiary::firstOrNew(['first_name' => $first, 'last_name' => $last, 'barangay_id' => $barangayId]);
            $beneficiary->fill([
                'middle_name' => $middle,
                'birthdate' => $beneficiary->birthdate ?? now()->subYears($age)->subMonths(2)->toDateString(),
                'address' => $address,
                'contact_number' => sprintf('0917-%03d-%04d', 310 + $i, 1200 + $i * 37),
                'rsbsa_number' => $number,
                'rsbsa_status' => $status,
                'source' => Beneficiary::SOURCE_MANUAL,
                'created_by' => ($extra['source'] ?? null) === Beneficiary::SOURCE_IMPORT ? $encoder : $admin,
                ...$extra,
            ]);

            if ($createdAt) {
                $beneficiary->created_at = Carbon::parse($createdAt);
            }

            // DatabaseSeeder mutes model events, so group the household explicitly.
            HouseholdService::assign($beneficiary);
            $beneficiary->save();
        }
    }
}
