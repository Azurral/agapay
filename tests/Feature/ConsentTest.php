<?php

use App\Models\Beneficiary;
use App\Models\ImportBatch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

/** Data Privacy Act (RA 10173): the farmer's consent is confirmed before AGAPAY keeps their information. */
it('shows the privacy notice on the landing page', function () {
    $this->get('/')->assertOk()
        ->assertSee('Data Privacy Act of 2012 (RA 10173)')
        ->assertSee('Only authorized OMAG staff can access it, and every change is recorded.');
});

it('asks for the farmer\'s consent on Add Beneficiary', function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);

    $this->actingAs(userWithRole(Role::ENCODER))->get('/beneficiaries/create')->assertOk()
        ->assertSee('The farmer has given consent for OMAG to record and use their personal information');
});

it('does not save a farmer without the consent checkbox', function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);

    $this->actingAs(userWithRole(Role::ENCODER))->post('/beneficiaries', validRegistration(['consent' => null]))
        ->assertSessionHasErrors(['consent' => 'Confirm that the farmer gave consent before saving.']);

    expect(Beneficiary::count())->toBe(0);
});

it('records who confirmed the consent and when, and shows it on the profile', function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
    $encoder = userWithRole(Role::ENCODER, ['first_name' => 'Sheryll Kate', 'last_name' => 'Bayang']);
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 30));

    $this->actingAs($encoder)->post('/beneficiaries', validRegistration());

    $farmer = Beneficiary::sole();
    expect($farmer->consent_confirmed_by)->toBe($encoder->id)
        ->and($farmer->consent_confirmed_at->format('Y-m-d H:i'))->toBe('2026-10-20 09:30');
    $this->get(route('beneficiaries.show', $farmer))->assertOk()
        ->assertSee('Consent confirmed by Sheryll Kate Bayang on Oct 20, 2026');
});

it('shows when consent was never recorded for an older profile', function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
    $admin = userWithRole(Role::ADMIN);
    $this->actingAs($admin)->post('/beneficiaries', validRegistration());
    $farmer = Beneficiary::sole();
    $farmer->forceFill(['consent_confirmed_at' => null, 'consent_confirmed_by' => null])->save();

    $this->get(route('beneficiaries.show', $farmer))->assertOk()->assertSee('Consent not recorded');
});

describe('Excel import', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->encoder = User::where('username', 'Encoder_03')->sole();
        $this->actingAs($this->encoder)->post('/import', ['file' => spreadsheet([
            ['Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Address', 'Contact'],
            ['Pablo Ramos', '1980-05-10', 'Poblacion', 'RSBSA-0901', 'Purok 2', 9171234567],
        ])]);
        $this->batch = ImportBatch::latest('id')->firstOrFail();
    });

    it('asks for one consent confirmation for the whole file', function () {
        $this->get(route('import.index', ['batch' => $this->batch]))->assertOk()
            ->assertSee('The farmers in this file gave consent for OMAG to record and use their personal information');
    });

    it('does not import without it', function () {
        $this->post(route('import.confirm', $this->batch))
            ->assertSessionHasErrors(['consent' => 'Confirm that the farmers in this file gave consent before importing.']);

        expect(Beneficiary::where('first_name', 'Pablo')->exists())->toBeFalse()
            ->and($this->batch->fresh()->isStaged())->toBeTrue();
    });

    it('records the confirmation on every new profile', function () {
        $this->post(route('import.confirm', $this->batch), ['consent' => '1'])->assertSessionHasNoErrors();

        $pablo = Beneficiary::where('first_name', 'Pablo')->sole();
        expect($pablo->consent_confirmed_by)->toBe($this->encoder->id)
            ->and($pablo->consent_confirmed_at)->not->toBeNull();
    });
});
