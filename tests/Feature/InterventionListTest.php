<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\InterventionRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

function seededRecord(string $first, string $last): InterventionRecord
{
    return InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where(['first_name' => $first, 'last_name' => $last]))->sole();
}

it('shows the two intervention types', function () {
    $this->actingAs($this->admin)->get('/interventions')->assertOk()
        ->assertSee('INTERVENTION TYPES')
        ->assertSeeInOrder(['Select Intervention Type', 'Department of Agriculture [DA]', 'National', 'View DA Beneficiary List',
            'Local Government Unit [LGU]', 'Municipal', 'View LGU Beneficiary List'])
        ->assertSee(route('interventions.da'), false)->assertSee(route('interventions.lgu'), false);
});

it('lists DA records with Figma columns for the administrator', function () {
    $this->actingAs($this->admin)->get('/interventions/da')->assertOk()
        ->assertSee('DA INTERVENTION LIST')
        ->assertSee('DA Intervention Beneficiaries')
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Poblacion', 'Certified Rice Seeds', '2 sacks', 'Eligible', 'Claimed'])
        ->assertSeeInOrder(['Liza Domingo', 'PAFF', 'Bedridden', 'Unclaimed'])
        ->assertSee('Archived/Restore')
        ->assertSee('>Archive<', false)
        ->assertDontSee('name="validation_status"', false)
        ->assertDontSee('Maria Santos')        // LGU record
        ->assertDontSee('Federico Wasing');    // archived record
});

it('gives agri techs validation and status dropdowns without archive controls', function () {
    $this->actingAs($this->agritech)->get('/interventions/da')->assertOk()
        ->assertSee('name="validation_status"', false)
        ->assertSee('name="claim_status"', false)
        ->assertDontSee('>Archive<', false)
        ->assertDontSee('Archived/Restore');
});

it('lists every farmer on the LGU page with address, farm area and crisis reports', function () {
    $juan = Beneficiary::where('rsbsa_number', 'RSBSA-0231')->sole();
    Beneficiary::where('first_name', 'Lorna')->sole()->delete();

    $this->actingAs($this->admin)->get('/interventions/lgu')->assertOk()
        ->assertSee('LGU Beneficiaries')
        ->assertSee('href="'.route('interventions.lgu', ['tab' => 'records']).'"', false)
        ->assertSeeInOrder(['Name', 'RSBSA', 'Address', 'Farm Area', 'Barangay', 'Crisis Reports'])
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Purok 3', $juan->farmAreaDisplay(), 'Poblacion', (string) $juan->damageReports()->count()])
        ->assertSeeInOrder(['Ana Gomez', 'N/A', 'Purok 2'])
        ->assertDontSee('Lorna Reyes');   // archived

    $this->get('/interventions/lgu?name=juan+dela')->assertSee('Juan Dela Cruz')->assertDontSee('Ana Gomez');
    $this->get('/interventions/lgu?barangay='.brgy('Samoki'))->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');
    $this->actingAs($this->agritech)->get('/interventions/lgu')->assertOk()->assertSee('LGU Beneficiaries');
});

it('counts only active crisis reports per farmer', function () {
    $juan = Beneficiary::where('rsbsa_number', 'RSBSA-0231')->sole();
    $before = $juan->damageReports()->count();
    $juan->damageReports()->firstOrFail()->delete();

    expect($juan->damageReports()->count())->toBe($before - 1)
        ->and(Beneficiary::withCount('damageReports')->find($juan->id)->damage_reports_count)->toBe($before - 1);
});

it('lists LGU program records like DA ones, with N/A for a missing RSBSA number', function () {
    $this->actingAs($this->admin)->get('/interventions/lgu?tab=records')->assertOk()
        ->assertSee('LGU INTERVENTION LIST')
        ->assertSeeInOrder(['Maria Santos', 'RSBSA-0198', 'Samoki', 'Complete Fertilizer'])
        ->assertSeeInOrder(['Pedro Reyes', 'N/A', 'Bontoc Ili', 'Emergency Seedlings'])
        ->assertSee('Qty / Unit')
        ->assertDontSee('Registered (New)');
});

it('filters by name, RSBSA, barangay and intervention', function () {
    $this->actingAs($this->admin);
    $paff = program('da', 'PAFF')->id;
    $samoki = Barangay::where('name', 'Samoki')->value('id');

    $this->get('/interventions/da?name=juan')->assertSee('Juan Dela Cruz')->assertDontSee('Rosa Mendez');
    $this->get('/interventions/da?rsbsa=0187')->assertSee('Rosa Mendez')->assertDontSee('Juan Dela Cruz');
    $this->get("/interventions/da?barangay={$samoki}")->assertSee('Rosa Mendez')->assertDontSee('Carlos Ibanez');
    $this->get("/interventions/da?intervention={$paff}")->assertSee('Liza Domingo')->assertDontSee('Juan Dela Cruz');
    $this->get('/interventions/da?name=nobody')->assertSee('No intervention records match these filters.');
});

it('treats hostile filters as plain text', function (string $query) {
    $this->actingAs($this->admin)->get('/interventions/lgu?'.$query)->assertOk();
})->with(['intervention[]=1', 'barangay=abc', 'registration=bogus', 'registration[]=new', 'name=%25', 'rsbsa=_', 'page=-3']);

it('404s an unknown source', function () {
    $this->actingAs($this->admin)->get('/interventions/xyz')->assertNotFound();
});

it('validates and claims through the dropdown routes', function () {
    $carlos = seededRecord('Carlos', 'Ibanez');
    $this->actingAs($this->agritech)->from('/interventions/da');

    $this->post(route('intervention-records.validate', $carlos), ['validation_status' => 'eligible'])
        ->assertRedirect('/interventions/da')->assertSessionHas('status', 'Carlos Ibanez - Complete Fertilizer (2026-Q3): Eligible.');
    $this->post(route('intervention-records.claim', $carlos))->assertSessionHasNoErrors();
    expect($carlos->fresh())->validation_status->toBe('eligible')->claim_status->toBe('claimed');

    $this->post(route('intervention-records.unclaim', $carlos))->assertSessionHasNoErrors();
    expect($carlos->fresh()->claim_status)->toBe('unclaimed');

    // Records start eligible: no validation step before a release.
    $this->post(route('intervention-records.claim', seededRecord('Pedro', 'Reyes')), ['quantity' => 5])->assertSessionHasNoErrors();
    $this->post(route('intervention-records.validate', $carlos), ['validation_status' => ['eligible']])
        ->assertSessionHasErrorsIn('intervention', 'validation_status');
});

it('has no beneficiary validation queue any more', function () {
    expect(Route::has('validation.index'))->toBeFalse()
        ->and(InterventionRecord::where('validation_status', 'pending')->exists())->toBeFalse();
    $this->actingAs($this->agritech)->get('/validation')->assertNotFound();
});

it('keeps lists and actions behind their permissions', function () {
    $carlos = seededRecord('Carlos', 'Ibanez');

    $this->actingAs($this->encoder)->get('/interventions/da')->assertForbidden();
    $this->actingAs($this->encoder)->post(route('intervention-records.validate', $carlos), ['validation_status' => 'eligible'])->assertForbidden();
});

it('turns a lock conflict during a claim into a try-again message', function () {
    $juan = seededRecord('Juan', 'Dela Cruz');
    $this->actingAs($this->admin)->post(route('intervention-records.unclaim', $juan));

    // Simulate InnoDB choosing this claim as a deadlock victim on every attempt.
    DB::connection()->beforeExecuting(function (string $sql) {
        if (str_contains($sql, 'update "intervention_records"')) {
            throw new QueryException('sqlite', $sql, [], new PDOException('Deadlock found when trying to get lock; try restarting transaction'));
        }
    });

    $this->actingAs($this->admin)->post(route('intervention-records.claim', $juan->fresh()))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'Another claim for this household was being saved at the same moment. Please try again.']);
});
