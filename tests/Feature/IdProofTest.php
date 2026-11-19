<?php

use App\Models\Beneficiary;
use App\Models\Role;
use App\Support\IdTextReader;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\InterventionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GpsJpeg;

// Proof of identity (panel, sir Ginard): a farmer added by hand needs a valid ID and, when they have an RSBSA No., the
// RSBSA / agricultural ID with their signature. AGAPAY checks what a program can check (a clear photo, not reused for
// another farmer, the name and number printed on it); the Administrator sees the results beside the photos.

beforeEach(function () {
    seedRoles();
    $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
    Storage::fake('local');
    $this->admin = userWithRole(Role::ADMIN);
    $this->encoder = userWithRole(Role::ENCODER);
});

/** The text reader (OCR) reads the given text from every photo. */
function ocrReads(?string $text): void
{
    app()->instance(IdTextReader::class, new class($text) extends IdTextReader
    {
        public function __construct(private readonly ?string $text) {}

        public function available(): bool
        {
            return true;
        }

        public function read(string $path): ?string
        {
            return $this->text;
        }
    });
}

it('needs the RSBSA / agricultural ID with signature when the farmer has an RSBSA No., and not otherwise', function () {
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['rsbsa_number' => 'RSBSA-0777', 'rsbsa_id_photo' => null]))
        ->assertSessionHasErrors(['rsbsa_id_photo' => "Attach a photo of the RSBSA / agricultural ID with the farmer's signature (JPG or PNG)."]);

    $this->post('/beneficiaries', validRegistration(['rsbsa_number' => '']))->assertSessionHasNoErrors();
    expect(Beneficiary::latest('id')->first()->rsbsa_id_photo)->toBeNull();

    $this->post('/beneficiaries', validRegistration(['first_name' => 'Pedro', 'rsbsa_number' => 'RSBSA-0777']))->assertSessionHasNoErrors();
    $pedro = Beneficiary::latest('id')->first();
    expect($pedro->rsbsa_id_photo)->not->toBeNull();
    Storage::disk('local')->assertExists($pedro->rsbsa_id_photo);
    $this->actingAs($this->admin)->get(route('beneficiaries.rsbsa-id', $pedro))->assertOk();
});

it('refuses an ID photo already used for another farmer', function () {
    $photo = GpsJpeg::bytes(null, null, 600, 380);
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['valid_id_photo' => UploadedFile::fake()->createWithContent('id.jpg', $photo)]))
        ->assertSessionHasNoErrors();

    $this->post('/beneficiaries', validRegistration([
        'first_name' => 'Pedro', 'valid_id_number' => 'VIN-9999-0000', 'valid_id_photo' => UploadedFile::fake()->createWithContent('same.jpg', $photo),
    ]))->assertSessionHasErrors(['valid_id_photo' => "This ID photo was already used for Juan Abenoja Dela Cruz. Take a photo of this farmer's own ID."]);
});

it('refuses an ID number already on another farmer', function () {
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['valid_id_number' => 'VIN-1234-5678']))->assertSessionHasNoErrors();

    $this->post('/beneficiaries', validRegistration(['first_name' => 'Pedro', 'valid_id_number' => 'vin 1234 5678']))
        ->assertSessionHasErrors(['valid_id_number' => "That Voter's ID number is already on Juan Abenoja Dela Cruz's profile."]);
});

it('reads the name and number printed on the IDs and shows the results to the administrator', function () {
    ocrReads("REPUBLIC OF THE PHILIPPINES\nDELA CRUZ, JUAN ABENOJA\nVIN 1234-5678 RSBSA-0777");
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['rsbsa_number' => 'RSBSA-0777', 'valid_id_number' => 'VIN-1234-5678']))->assertSessionHasNoErrors();

    $juan = Beneficiary::latest('id')->first();
    expect(collect($juan->id_checks)->pluck('result')->unique()->all())->toBe(['pass'])
        ->and(collect($juan->id_checks)->pluck('check'))->toContain("Voter's ID: name Juan Dela Cruz printed on the ID")
        ->toContain('RSBSA / agricultural ID: number RSBSA-0777 printed on the ID');

    $this->actingAs($this->admin)->get('/beneficiaries/approvals')->assertOk()
        ->assertSee('Automatic checks')
        ->assertSee('Voter&#039;s ID: name Juan Dela Cruz printed on the ID', false)
        ->assertSee(route('beneficiaries.rsbsa-id', $juan), false);
});

it('flags an ID whose printed name is someone else, for the administrator', function () {
    ocrReads("DELA CRUZ, PEDRO\nVIN 1234-5678");
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration(['valid_id_number' => 'VIN-1234-5678']))->assertSessionHasNoErrors();

    $checks = collect(Beneficiary::latest('id')->first()->id_checks)->pluck('result', 'check');
    expect($checks["Voter's ID: name Juan Dela Cruz printed on the ID"])->toBe('fail')
        ->and($checks["Voter's ID: number VIN-1234-5678 printed on the ID"])->toBe('pass');
    $this->actingAs($this->admin)->get('/beneficiaries/approvals')->assertSee('1 check failed');
});

it('says the IDs were not read when the text reader is not installed', function () {
    $this->actingAs($this->encoder)->post('/beneficiaries', validRegistration())->assertSessionHasNoErrors();

    expect(collect(Beneficiary::latest('id')->first()->id_checks)->pluck('result', 'check')->all())
        ->toMatchArray(["Voter's ID: name on the ID (text reader (OCR) not installed)" => 'unknown']);
});
