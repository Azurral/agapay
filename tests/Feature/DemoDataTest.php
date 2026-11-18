<?php

use App\Models\AssistanceRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\DamageReport;
use App\Models\InterventionRecord;
use Database\Seeders\DatabaseSeeder;

// Panel: "generated hypothetical data which answers all fields; there must be a lot, to show it is really needed."

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('adds many made-up farmers across every barangay, with every field filled', function () {
    $before = Beneficiary::count();

    $this->artisan('agapay:demo-data', ['--force' => true])->assertSuccessful();

    $demo = Beneficiary::where('source', 'demo')->get();
    expect($demo)->toHaveCount(120)
        ->and(Beneficiary::count())->toBe($before + 120)
        ->and($demo->pluck('barangay_id')->unique()->count())->toBe(Barangay::count());

    foreach (['first_name', 'last_name', 'birthdate', 'house_no', 'street', 'sitio', 'contact_number', 'farm_area_ha', 'crop_type',
        'farm_latitude', 'farm_longitude', 'valid_id_type', 'valid_id_number', 'consent_confirmed_at'] as $field) {
        expect($demo->whereNull($field)->count())->toBe(0, "{$field} is empty");
    }
    expect($demo->whereNotNull('rsbsa_number')->count())->toBeGreaterThan(90)
        ->and($demo->every(fn ($f) => $f->age() >= 18))->toBeTrue();
});

it('gives the farmers program records, claims and requests', function () {
    $this->artisan('agapay:demo-data', ['--force' => true])->assertSuccessful();
    $ids = Beneficiary::where('source', 'demo')->pluck('id');

    $records = InterventionRecord::whereIn('beneficiary_id', $ids)->get();
    expect($records->count())->toBeGreaterThan(150)
        ->and($records->where('claim_status', 'claimed')->count())->toBeGreaterThan(40)
        ->and($records->where('claim_status', 'unclaimed')->count())->toBeGreaterThan(40)
        ->and(AssistanceRequest::whereIn('beneficiary_id', $ids)->count())->toBeGreaterThan(10);
});

it('grows to a larger count for the scalability test, adding only what is missing', function () {
    $this->artisan('agapay:demo-data', ['--force' => true])->assertSuccessful();
    $this->artisan('agapay:demo-data', ['--force' => true, '--count' => 300])->assertSuccessful();

    $demo = Beneficiary::where('source', 'demo');
    expect($demo->count())->toBe(300)
        ->and((clone $demo)->distinct()->count('rsbsa_number'))->toBe((clone $demo)->whereNotNull('rsbsa_number')->count())   // no duplicate RSBSA No.
        ->and(DamageReport::whereIn('beneficiary_id', (clone $demo)->select('id'))->count())->toBeGreaterThan(20);
});

it('runs only once and only with --force', function () {
    $this->artisan('agapay:demo-data')->assertFailed();
    $this->artisan('agapay:demo-data', ['--force' => true])->assertSuccessful();
    $this->artisan('agapay:demo-data', ['--force' => true])->assertSuccessful();

    expect(Beneficiary::where('source', 'demo')->count())->toBe(120);
});
