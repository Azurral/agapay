<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Services\HouseholdService;
use Database\Seeders\BarangaySeeder;

beforeEach(fn () => $this->seed(BarangaySeeder::class));

it('seeds the 16 Bontoc barangays', function () {
    expect(Barangay::orderBy('id')->pluck('name')->all())->toBe(Barangay::NAMES);
});

it('normalizes address spelling variants to one key', function (string $address) {
    expect(HouseholdService::normalizeAddress($address, 'Poblacion'))->toBe('purok 3');
})->with(['Purok 3, Barangay Poblacion', 'purok 3 brgy. poblacion', '  PUROK   3 ', 'Purok 3, Bgy Poblacion']);

it('groups beneficiaries sharing an address into one household', function () {
    $poblacion = Barangay::firstWhere('name', 'Poblacion');
    $juan = Beneficiary::factory()->create(['address' => 'Purok 3, Barangay Poblacion', 'barangay_id' => $poblacion->id]);
    $maria = Beneficiary::factory()->create(['address' => 'purok 3', 'barangay_id' => $poblacion->id]);
    $other = Beneficiary::factory()->create(['address' => 'Purok 4', 'barangay_id' => $poblacion->id]);

    expect($maria->household_id)->toBe($juan->household_id)
        ->and($other->household_id)->not->toBe($juan->household_id)
        ->and($juan->fresh()->householdSize())->toBe(2)
        ->and($juan->household->household_no)->toMatch('/^HH-\d{5}$/')
        ->and($juan->otherHouseholdMembers()->pluck('id')->all())->toBe([$maria->id]);
});

it('treats the same address in another barangay as another household', function () {
    $a = Beneficiary::factory()->create(['address' => 'Purok 3', 'barangay_id' => Barangay::firstWhere('name', 'Poblacion')->id]);
    $b = Beneficiary::factory()->create(['address' => 'Purok 3', 'barangay_id' => Barangay::firstWhere('name', 'Samoki')->id]);

    expect($b->household_id)->not->toBe($a->household_id)
        ->and($a->fresh()->householdSize())->toBe(1);
});

it('moves a beneficiary to a new household when the address changes', function () {
    $poblacion = Barangay::firstWhere('name', 'Poblacion')->id;
    $juan = Beneficiary::factory()->create(['address' => 'Purok 3', 'barangay_id' => $poblacion]);
    $maria = Beneficiary::factory()->create(['address' => 'Purok 3', 'barangay_id' => $poblacion]);
    $oldHousehold = $maria->household_id;

    $maria->update(['address' => 'Purok 9']);

    expect($maria->fresh()->household_id)->not->toBe($oldHousehold)
        ->and($juan->fresh()->householdSize())->toBe(1);
});

it('labels beneficiaries for screens and the audit trail', function () {
    $b = Beneficiary::factory()->create([
        'first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz',
        'rsbsa_number' => 'RSBSA-0231', 'birthdate' => now()->subYears(45)->subDay(),
    ]);

    expect($b->fullName())->toBe('Juan Dela Cruz')
        ->and($b->age())->toBe(45)
        ->and($b->auditRecordLabel())->toBe('Juan Dela Cruz (RSBSA-0231)')
        ->and(Beneficiary::factory()->make(['rsbsa_number' => null])->rsbsaDisplay())->toBe('(pending)');
});
