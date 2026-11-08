<?php

use App\Models\Beneficiary;
use App\Services\HouseholdService;
use Database\Seeders\BarangaySeeder;

// Basis path testing of HouseholdService::assign (manuscript section 3.4.2). Decisions:
//   P1 address names only the barangay (nothing to group on)?  P2 keep the farmer's current one-person household?
//   P3 household has no number yet?
// V(G) = 3 + 1 = 4 independent paths.

beforeEach(function () {
    $this->seed(BarangaySeeder::class);
    $this->farmer = fn (string $address, string $barangay = 'Poblacion') => Beneficiary::factory()->make(['address' => $address, 'barangay_id' => brgy($barangay)]);
});

it('path 1: P1 false, P3 true - a new address starts a numbered household', function () {
    $household = HouseholdService::assign(($this->farmer)('Purok 3'));

    expect($household->address_key)->toBe('purok 3')->and($household->household_no)->toBe(sprintf('HH-%05d', $household->id));
});

it('path 2: P1 false, P3 false - a neighbour at the same address joins that household', function () {
    $first = HouseholdService::assign(($this->farmer)('Purok 3'));

    expect(HouseholdService::assign(($this->farmer)('purok 3, Poblacion, Bontoc'))->id)->toBe($first->id);
});

it('path 3: P1 true, P2 false - an address naming only the barangay gets its own household', function () {
    $a = HouseholdService::assign(($this->farmer)('Barangay Poblacion'));
    $b = HouseholdService::assign(($this->farmer)('Poblacion'));

    expect($a->id)->not->toBe($b->id)->and($a->address_key)->toStartWith('~solo:');
});

it('path 4: P1 true, P2 true - saving that farmer again keeps the same household', function () {
    $farmer = ($this->farmer)('Barangay Poblacion');
    $first = HouseholdService::assign($farmer);

    expect(HouseholdService::assign($farmer)->id)->toBe($first->id);
});
