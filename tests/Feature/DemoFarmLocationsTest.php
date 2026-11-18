<?php

use App\Models\Beneficiary;
use Database\Seeders\DatabaseSeeder;

// A demo copy made before farm locations existed (the cloud demo): its sample farmers get a made-up farm position in
// their barangay, so the photo GPS check can be shown. Only on demand, and only for farmers without a location.

it('gives sample farmers without a farm location one in their barangay', function () {
    $this->seed(DatabaseSeeder::class);
    Beneficiary::query()->update(['farm_latitude' => null, 'farm_longitude' => null]);
    $kept = Beneficiary::firstOrFail();
    $kept->forceFill(['farm_latitude' => 17.5, 'farm_longitude' => 121.5])->saveQuietly();

    $this->artisan('agapay:demo-farm-locations', ['--force' => true])->assertSuccessful();

    expect(Beneficiary::whereNull('farm_latitude')->count())->toBe(0)
        ->and((float) $kept->fresh()->farm_latitude)->toBe(17.5);   // an existing location is never changed
});

it('refuses without --force, so a real copy is never touched by accident', function () {
    $this->artisan('agapay:demo-farm-locations')->assertFailed();
});
