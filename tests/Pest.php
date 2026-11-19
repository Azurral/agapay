<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\GpsJpeg;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Seed the three roles and their default permissions. */
function seedRoles(): void
{
    test()->seed(RolePermissionSeeder::class);
}

/** Create a user holding the given role slug (roles must be seeded). */
function userWithRole(?string $slug, array $attributes = []): User
{
    return User::factory()->create([
        'role_id' => $slug ? Role::where('slug', $slug)->value('id') : null,
        ...$attributes,
    ]);
}

/** Id of a seeded barangay by name (BarangaySeeder must have run). */
function brgy(string $name): int
{
    return Barangay::where('name', $name)->valueOrFail('id');
}

/**
 * Beneficiaries sharing one household (same address in Poblacion), e.g. sameHousehold(['Juan', 'Maria']).
 *
 * @param  list<string>  $firstNames
 * @return list<Beneficiary>
 */
function sameHousehold(array $firstNames, string $lastName = 'Dela Cruz'): array
{
    return array_map(fn (string $first) => Beneficiary::factory()->create([
        'first_name' => $first, 'middle_name' => null, 'last_name' => $lastName,
        'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion'),
    ]), $firstNames);
}

/** An intervention record for the beneficiary in the current cycle (InterventionSeeder must have run). */
function record(Beneficiary $beneficiary, Intervention $intervention, array $attributes = []): InterventionRecord
{
    return InterventionRecord::factory()->create([
        'beneficiary_id' => $beneficiary->id, 'intervention_id' => $intervention->id, ...$attributes,
    ]);
}

/** A seeded intervention by source and name. */
function program(string $source, string $name): Intervention
{
    return Intervention::where(['source' => $source, 'name' => $name])->sole();
}

/**
 * A temporary uploaded spreadsheet built from rows of cell values (0-based, row-major).
 * A cell given as ['error' => '#REF!'] is written as an Excel error value; null leaves the cell empty.
 */
function spreadsheet(array $rows, string $type = 'xlsx', string $name = 'masterlist'): UploadedFile
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();

    foreach ($rows as $r => $row) {
        foreach ($row as $c => $value) {
            $coordinate = Coordinate::stringFromColumnIndex($c + 1).($r + 1);
            if (is_array($value) && isset($value['error'])) {
                $sheet->setCellValueExplicit($coordinate, $value['error'], DataType::TYPE_ERROR);
            } elseif ($value !== null) {
                $sheet->setCellValue($coordinate, $value);
            }
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'agapay').'.'.$type;
    IOFactory::createWriter($book, $type === 'csv' ? 'Csv' : 'Xlsx')->save($path);

    return new UploadedFile($path, "{$name}.{$type}", null, null, true);
}

/** Valid Add Beneficiary input (BarangaySeeder must have run). */
function validRegistration(array $overrides = []): array
{
    $input = [
        'first_name' => 'Juan',
        'middle_name' => 'Abenoja',
        'last_name' => 'Dela Cruz',
        'birthdate' => now()->subYears(45)->toDateString(),
        'house_no' => null,
        'street' => null,
        'sitio' => 'Purok 3',
        'barangay_id' => brgy('Poblacion'),
        'contact_number' => '0917-123-4567',
        'farm_area_ha' => '1.5',
        'crop_type' => 'Cabbage',
        'consent' => '1',
        'farm_latitude' => '17.0894',
        'farm_longitude' => '120.9770',
        // Walk-in verification (no ghost farmers): a valid ID with its number and a photo (each photo different, as a
        // reused photo is refused).
        'valid_id_type' => 'voters',
        'valid_id_number' => 'VIN-'.random_int(1000, 9999).'-'.random_int(1000, 9999),   // each farmer's own ID
        'valid_id_photo' => idPhoto('valid-id.jpg'),
        ...$overrides,
    ];
    // A farmer with an RSBSA No. also shows the RSBSA / agricultural ID with signature.
    if (! array_key_exists('rsbsa_id_photo', $overrides) && trim((string) ($input['rsbsa_number'] ?? '')) !== '') {
        $input['rsbsa_id_photo'] = idPhoto('rsbsa-id.jpg');
    }

    return array_filter($input, fn ($value, $key) => ! ($key === 'rsbsa_id_photo' && $value === null), ARRAY_FILTER_USE_BOTH);
}

/** A photo of an ID card, different every time. */
function idPhoto(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, GpsJpeg::bytes(null, null, 600, 380));
}
