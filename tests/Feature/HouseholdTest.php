<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Services\HouseholdService;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\BeneficiarySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;

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
        ->and(Beneficiary::factory()->make(['rsbsa_number' => null])->rsbsaDisplay())->toBe('N/A');
});

it('seeds the Figma sample beneficiaries', function () {
    $this->seed([RolePermissionSeeder::class, BarangaySeeder::class, UserSeeder::class]);
    $this->seed(BeneficiarySeeder::class);
    $this->seed(BeneficiarySeeder::class);

    $find = fn (string $first, string $last) => Beneficiary::where('first_name', $first)->where('last_name', $last)->sole();

    expect(Beneficiary::count())->toBe(13)
        ->and($find('Juan', 'Dela Cruz')->householdSize())->toBe(3)
        ->and($find('Juan', 'Dela Cruz')->age())->toBe(45)
        ->and($find('Pedro', 'Reyes')->householdSize())->toBe(2)
        ->and($find('Carlos', 'Ibanez')->householdSize())->toBe(2)
        ->and(Beneficiary::whereNotNull('encoding_issue')->count())->toBe(1)
        ->and(Beneficiary::whereNull('rsbsa_number')->count())->toBe(4)
        ->and($find('Federico', 'Wasing')->created_at->toDateString())->toBe('2026-07-20');
});

it('groups seeded households even when model events are muted', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Beneficiary::where('first_name', 'Juan')->where('last_name', 'Dela Cruz')->sole()->householdSize())->toBe(3)
        ->and(Beneficiary::whereNull('household_id')->count())->toBe(0);
});

it('never groups people whose address is only the barangay name', function () {
    $poblacion = Barangay::where('name', 'Poblacion')->value('id');
    $a = Beneficiary::factory()->create(['address' => 'Poblacion', 'barangay_id' => $poblacion]);
    $b = Beneficiary::factory()->create(['address' => 'Brgy. Poblacion', 'barangay_id' => $poblacion]);

    expect($a->household_id)->not->toBe($b->household_id)
        ->and($a->householdSize())->toBe(1);

    // Re-saving keeps the person's own household instead of minting a new one.
    $before = $a->household_id;
    $a->update(['address' => 'Barangay Poblacion']);
    expect($a->fresh()->household_id)->toBe($before);
});

it('ignores the municipality and province in addresses', function () {
    $poblacion = Barangay::where('name', 'Poblacion')->value('id');
    $a = Beneficiary::factory()->create(['address' => 'Purok 3', 'barangay_id' => $poblacion]);
    $b = Beneficiary::factory()->create(['address' => 'Purok 3, Poblacion, Bontoc, Mt. Province', 'barangay_id' => $poblacion]);
    $c = Beneficiary::factory()->create(['address' => 'Purok 3 Bontoc Mountain Province', 'barangay_id' => $poblacion]);

    expect($b->household_id)->toBe($a->household_id)->and($c->household_id)->toBe($a->household_id);
});
