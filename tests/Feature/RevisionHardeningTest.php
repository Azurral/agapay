<?php

use App\Models\Beneficiary;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Services\ExcelImportService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

it('keeps the LGU program records tab when its filters are applied', function () {
    $this->actingAs($this->admin)->get('/interventions/lgu?tab=records')
        ->assertSee('<input type="hidden" name="tab" value="records">', false);

    $this->get('/interventions/lgu?tab=records&barangay='.brgy('Samoki'))
        ->assertSee('Qty / Unit')->assertSee('Maria Santos')->assertDontSee('Pedro Reyes');
});

it('links the LGU archive toggle back to the program records', function () {
    $this->actingAs($this->admin)->get('/interventions/lgu/archived')
        ->assertSee('href="'.route('interventions.lgu', ['tab' => 'records']).'"', false)
        ->assertSee('>Records<', false);
});

it('reads a farm area only from a number of hectares', function (?string $text, ?string $hectares) {
    expect(Beneficiary::hectaresIn($text))->toBe($hectares);
})->with([
    'with unit' => ['Poblacion (1.5 hectares)', '1.5'],
    'short unit' => ['2 ha', '2'],
    'sitio number' => ['Sitio Ili 2', null],
    'purok number' => ['Purok 3', null],
    'square meters' => ['500 sqm', null],
    'blank' => [null, null],
]);

it('treats N/A and similar placeholders as no RSBSA number', function (string $typed) {
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['rsbsa_number' => $typed]))->assertSessionHasNoErrors();
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['first_name' => 'Pedro', 'rsbsa_number' => $typed]))->assertSessionHasNoErrors();

    expect(Beneficiary::where('last_name', 'Dela Cruz')->where('first_name', 'Juan')->latest('id')->first()->rsbsa_number)->toBeNull()
        ->and(Beneficiary::normalizeRsbsa($typed))->toBeNull();
})->with(['N/A', 'n/a', 'NA', 'none', '-', '(pending)']);

it('imports N/A as no RSBSA number and skips its DA program', function () {
    $batch = app(ExcelImportService::class)->stage(spreadsheet([
        ['Name', 'Birthdate', 'Barangay', 'RSBSA No.', 'Intervention'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', 'N/A', 'DA - Certified Rice Seeds'],
        ['Ben Talawec', '1975-01-03', 'Samoki', 'n/a', null],
    ]), $this->encoder);

    $rows = $batch->rows()->orderBy('row_number')->get();
    expect($rows[0]->status)->toBe('ready')->and($rows[0]->data['rsbsa_number'])->toBeNull()->and($rows[0]->data['intervention_id'])->toBeNull()
        ->and($rows[1]->status)->toBe('ready');
});

it('clears an encoding issue when the encoder saves the profile', function () {
    $estrella = Beneficiary::where('first_name', 'Estrella')->sole();   // "Awaiting Barangay Confirmation"
    $this->actingAs($this->encoder)->get('/dashboard')->assertSee('Estrella Domogen');

    $this->put(route('beneficiaries.update', $estrella), [
        'first_name' => $estrella->first_name, 'last_name' => $estrella->last_name, 'birthdate' => $estrella->birthdate->toDateString(),
        'sitio' => $estrella->sitio, 'barangay_id' => $estrella->barangay_id,
    ])->assertSessionHasNoErrors();

    expect($estrella->fresh()->encoding_issue)->toBeNull();
    $this->get('/dashboard')->assertDontSee('Estrella Domogen');
});

it('keeps long one-line addresses whole', function () {
    $address = str_repeat('Long address part ', 12);   // over 200 characters
    $b = Beneficiary::factory()->create(['address' => $address]);
    $b->update(['barangay_id' => brgy('Samoki')]);

    expect($b->fresh()->address)->toBe(trim($address))->and($b->fresh()->sitio)->toBe(trim($address));
});

it('reads separate house number, street and purok columns', function () {
    $batch = app(ExcelImportService::class)->stage(spreadsheet([
        ['Name', 'Birthdate', 'Barangay', 'House No.', 'Street', 'Purok'],
        ['Pablo Ramos', '1980-05-10', 'Poblacion', '12', 'Rizal St.', 'Purok 2'],
    ]), $this->encoder);

    expect($batch->rows()->sole()->data)->toMatchArray(['house_no' => '12', 'street' => 'Rizal St.', 'sitio' => 'Purok 2']);
    $this->actingAs($this->encoder)->post(route('import.confirm', $batch));
    expect(Beneficiary::where('first_name', 'Pablo')->sole()->address)->toBe('12, Rizal St., Purok 2');
});

it('does not offer a DA program for release to a farmer without an RSBSA number', function () {
    $federico = Beneficiary::where('first_name', 'Federico')->sole();
    InterventionRecord::onlyTrashed()->where('beneficiary_id', $federico->id)->sole()->restore();

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $federico))
        ->assertSee('title="No claimable interventions."', false);
});
