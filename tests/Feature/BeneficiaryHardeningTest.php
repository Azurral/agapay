<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Household;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

/** The profile edit form's fields for a beneficiary, with overrides. */
function editInput(Beneficiary $b, array $overrides = []): array
{
    return [
        'first_name' => $b->first_name, 'middle_name' => $b->middle_name, 'last_name' => $b->last_name,
        'birthdate' => $b->birthdate->toDateString(), 'house_no' => $b->house_no, 'street' => $b->street, 'sitio' => $b->sitio,
        'barangay_id' => $b->barangay_id, 'rsbsa_number' => $b->rsbsa_number, 'contact_number' => $b->contact_number,
        'farm_area_ha' => $b->farm_area_ha, 'crop_type' => $b->crop_type,
        // A one-line "address" override is the sitio/purok.
        ...(isset($overrides['address']) ? ['sitio' => $overrides['address']] : []),
        ...collect($overrides)->except('address')->all(),
    ];
}

it('refuses an edit that duplicates another profile', function () {
    $juan = Beneficiary::where('rsbsa_number', 'RSBSA-0231')->sole();
    $maria = Beneficiary::where('rsbsa_number', 'RSBSA-0232')->sole();

    $this->actingAs($this->encoder)->put(route('beneficiaries.update', $maria), editInput($maria, [
        'first_name' => 'juan', 'last_name' => 'DELA CRUZ', 'birthdate' => $juan->birthdate->toDateString(), 'barangay_id' => $juan->barangay_id,
    ]))->assertSessionHasErrors(['first_name' => 'Another profile already has this name, birthdate and barangay.']);

    expect($maria->fresh()->first_name)->toBe('Maria');
});

it('stores names without extra spaces', function () {
    $b = Beneficiary::factory()->create(['first_name' => '  Rosa  ', 'middle_name' => ' Ana   Lee ', 'last_name' => "Dela\t Cruz ", 'house_no' => ' 12 ', 'street' => ' Rizal   St. ', 'sitio' => ' Purok  3 ']);

    expect($b->fresh())->first_name->toBe('Rosa')->middle_name->toBe('Ana Lee')->last_name->toBe('Dela Cruz')
        ->sitio->toBe('Purok 3')->street->toBe('Rizal St.')->address->toBe('12, Rizal St., Purok 3');
});

it('stores RSBSA numbers in upper case', function () {
    $b = Beneficiary::factory()->create(['rsbsa_number' => ' rsbsa-0777 ']);
    $blank = Beneficiary::factory()->create(['rsbsa_number' => '  ']);

    expect($b->fresh()->rsbsa_number)->toBe('RSBSA-0777')->and($blank->fresh()->rsbsa_number)->toBeNull();
});

it('normalises existing rows with the data migration', function () {
    DB::table('beneficiaries')->where('rsbsa_number', 'RSBSA-0231')->update(['first_name' => ' Juan  ', 'rsbsa_number' => 'rsbsa-0231 ']);

    (require database_path('migrations/2026_10_07_000001_normalise_beneficiary_text.php'))->up();

    expect(DB::table('beneficiaries')->where('rsbsa_number', 'RSBSA-0231')->value('first_name'))->toBe('Juan');
});

it('turns a simultaneous RSBSA number into a form error', function () {
    $federico = Beneficiary::where('first_name', 'Federico')->sole();   // no number yet
    // Someone else saves the same number between validation and this save.
    Beneficiary::saving(function (Beneficiary $b) use ($federico) {
        if ($b->is($federico) && $b->isDirty('rsbsa_number') && ! DB::table('beneficiaries')->where('rsbsa_number', 'RSBSA-0999')->exists()) {
            DB::table('beneficiaries')->where('first_name', 'Estrella')->update(['rsbsa_number' => 'RSBSA-0999']);
        }
    });

    $this->actingAs($this->encoder)->from(route('beneficiaries.show', $federico))
        ->put(route('beneficiaries.update', $federico), editInput($federico, ['rsbsa_number' => 'RSBSA-0999']))
        ->assertSessionHasErrors(['rsbsa_number' => 'RSBSA No. RSBSA-0999 is already used by another profile.']);
});

it('finds people by full name in any order', function (string $term) {
    Beneficiary::factory()->create(['first_name' => 'Rosalie', 'middle_name' => 'Bagni', 'last_name' => 'Tamayo']);

    $this->actingAs($this->admin)->get('/search?q='.urlencode($term))->assertSee('Rosalie Bagni Tamayo');
})->with(['Rosalie Bagni Tamayo', 'Rosalie Tamayo', 'Tamayo Rosalie', 'Tamayo, Rosalie', 'tamayo,rosalie']);

it('hides the search bar without beneficiaries.view', function () {
    $this->encoder->role->permissions()->detach(Permission::where('slug', 'beneficiaries.view')->value('id'));

    $this->actingAs($this->encoder->fresh())->get('/dashboard')->assertOk()->assertDontSee('role="search"', false);
    $this->actingAs($this->admin)->get('/dashboard')->assertSee('role="search"', false);
});

it('loads the barangay list once', function () {
    $this->actingAs($this->admin)->get('/dashboard');
    DB::enableQueryLog();
    $this->get('/dashboard');

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'from "barangays"') && ! str_contains($q, 'where'))->count())->toBe(0);

    Barangay::create(['name' => 'Test Barangay']);
    $this->get('/dashboard')->assertSee('Test Barangay');
});

it('removes a household left empty', function () {
    $b = Beneficiary::factory()->create(['address' => 'Purok 9 Lonely', 'barangay_id' => Barangay::where('name', 'Poblacion')->value('id')]);
    $old = $b->household_id;

    $b->update(['address' => 'Purok 3']);

    expect(Household::find($old))->toBeNull()->and($b->fresh()->household_id)->not->toBe($old);
});

it('keeps a household that an archived member still belongs to', function () {
    $poblacion = Barangay::where('name', 'Poblacion')->value('id');
    $a = Beneficiary::factory()->create(['address' => 'Purok 8 Shared', 'barangay_id' => $poblacion]);
    $b = Beneficiary::factory()->create(['address' => 'Purok 8 Shared', 'barangay_id' => $poblacion]);
    $household = $a->household_id;
    $a->delete();

    $b->update(['address' => 'Purok 3']);

    expect(Household::find($household))->not->toBeNull();
});

it('keeps the old case when upper-casing would collide', function () {
    DB::table('beneficiaries')->where('rsbsa_number', 'RSBSA-0232')->update(['rsbsa_number' => 'rsbsa-0231']);

    (require database_path('migrations/2026_10_07_000001_normalise_beneficiary_text.php'))->up();

    expect(DB::table('beneficiaries')->where('rsbsa_number', 'rsbsa-0231')->exists())->toBeTrue()
        ->and(DB::table('beneficiaries')->where('rsbsa_number', 'RSBSA-0231')->count())->toBe(1);
});

it('does not report other unique clashes as an RSBSA clash', function () {
    $maria = Beneficiary::where('rsbsa_number', 'RSBSA-0232')->sole();
    Beneficiary::saving(fn () => throw new UniqueConstraintViolationException('mysql', 'update', [], new PDOException('Duplicate entry for key households_address_unique', 23000)));

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->encoder)->put(route('beneficiaries.update', $maria), editInput($maria)))
        ->toThrow(UniqueConstraintViolationException::class);
});
