<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Role;
use App\Services\ExcelImportService;
use Database\Seeders\BarangaySeeder;

beforeEach(function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
});

it('joins the address parts like a government form', function () {
    $b = Beneficiary::factory()->create([
        'house_no' => ' 12 ', 'street' => 'Rizal  St.', 'sitio' => 'Purok 3', 'barangay_id' => brgy('Poblacion'),
    ]);

    expect($b->fresh())
        ->house_no->toBe('12')->street->toBe('Rizal St.')->sitio->toBe('Purok 3')
        ->address->toBe('12, Rizal St., Purok 3')
        ->fullAddress()->toBe('12, Rizal St., Purok 3, Barangay Poblacion, Bontoc, Mountain Province');
});

it('leaves out empty parts', function () {
    $b = Beneficiary::factory()->create(['house_no' => null, 'street' => '', 'sitio' => 'Sitio Maligcong', 'barangay_id' => brgy('Maligcong')]);

    expect($b->fresh())->address->toBe('Sitio Maligcong')
        ->fullAddress()->toBe('Sitio Maligcong, Barangay Maligcong, Bontoc, Mountain Province');
});

it('still groups households from the joined parts', function () {
    $juan = Beneficiary::factory()->create(['house_no' => '12', 'street' => 'Rizal St.', 'sitio' => 'Purok 3', 'barangay_id' => brgy('Poblacion')]);
    $maria = Beneficiary::factory()->create(['house_no' => '12', 'street' => 'rizal st', 'sitio' => 'purok 3', 'barangay_id' => brgy('Poblacion')]);
    $ana = Beneficiary::factory()->create(['house_no' => '14', 'street' => 'Rizal St.', 'sitio' => 'Purok 3', 'barangay_id' => brgy('Poblacion')]);

    expect($maria->household_id)->toBe($juan->household_id)
        ->and($ana->household_id)->not->toBe($juan->household_id);
});

it('adds a beneficiary with address parts and a farm area', function () {
    $this->actingAs(userWithRole(Role::ENCODER))->post('/beneficiaries', validRegistration([
        'house_no' => '12', 'street' => 'Rizal St.', 'sitio' => 'Purok 3', 'farm_area_ha' => '1.5',
    ]))->assertSessionHasNoErrors();

    expect(Beneficiary::sole())->address->toBe('12, Rizal St., Purok 3')->farm_area_ha->toEqual('1.50');
});

it('requires the sitio and a farm area in hectares', function () {
    $this->actingAs(userWithRole(Role::ENCODER))->post('/beneficiaries', validRegistration(['sitio' => '', 'farm_area_ha' => 'one hectare']))
        ->assertSessionHasErrors([
            'sitio' => 'Enter the sitio or purok.',
            'farm_area_ha' => 'Enter the farm area in hectares, e.g. 1.5.',
        ]);

    expect(Beneficiary::count())->toBe(0);
});

it('shows the address parts, the locked town and the farm area on the form', function () {
    $this->actingAs(userWithRole(Role::ENCODER))->get('/beneficiaries/create')
        ->assertSeeInOrder(['House/Lot No.', 'Street', 'Sitio/Purok', 'Barangay', 'Municipality', 'Province', 'Region', 'Farm Area (ha)'])
        ->assertSee('value="Bontoc"', false)
        ->assertSee('value="Mountain Province"', false)
        ->assertDontSee('Farm Location');
});

it('edits the address parts and farm area on the profile', function () {
    $b = Beneficiary::factory()->create(['sitio' => 'Purok 3', 'barangay_id' => brgy('Poblacion')]);
    $input = [
        'first_name' => $b->first_name, 'middle_name' => $b->middle_name, 'last_name' => $b->last_name,
        'birthdate' => $b->birthdate->toDateString(), 'barangay_id' => $b->barangay_id, 'rsbsa_number' => $b->rsbsa_number,
        'house_no' => '7', 'street' => 'Bayyo Road', 'sitio' => 'Purok 9', 'farm_area_ha' => '0.75',
    ];

    $this->actingAs(userWithRole(Role::ENCODER))->put(route('beneficiaries.update', $b), $input)->assertSessionHasNoErrors();

    expect($b->fresh())->address->toBe('7, Bayyo Road, Purok 9')->farm_area_ha->toEqual('0.75');
    $this->actingAs(userWithRole(Role::ADMIN))->get(route('beneficiaries.show', $b))
        ->assertSee('7, Bayyo Road, Purok 9, Barangay Poblacion, Bontoc, Mountain Province')
        ->assertSeeInOrder(['Farm Area', '0.75 ha']);
});

it('reads a farm area column from Excel', function () {
    $encoder = userWithRole(Role::ENCODER);
    $batch = app(ExcelImportService::class)->stage(spreadsheet([
        ['Name', 'Birthdate', 'Barangay', 'Address', 'Farm Area'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'Purok 2', '1.25'],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'Purok 1', 'big'],
    ]), $encoder);

    expect($batch->rows()->where('row_number', 2)->sole()->data)->toMatchArray(['sitio' => 'Purok 2', 'farm_area_ha' => '1.25'])
        ->and($batch->rows()->where('row_number', 3)->sole())->status->toBe('ready')
        ->issues->toBe(["Farm area 'big' ignored (not a number of hectares)"]);
});

it('fills a blank address with the barangay', function () {
    $batch = app(ExcelImportService::class)->stage(spreadsheet([
        ['Name', 'Birthdate', 'Barangay'], ['Pablo Ramos', '1980-05-10', 'Poblacion'],
    ]), userWithRole(Role::ENCODER));

    expect($batch->rows()->sole()->data['sitio'])->toBe('Barangay '.Barangay::find(brgy('Poblacion'))->name);
});
