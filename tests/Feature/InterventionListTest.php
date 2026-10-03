<?php

use App\Models\Barangay;
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

it('adds the registration column and filter on LGU', function () {
    $this->actingAs($this->admin)->get('/interventions/lgu')->assertOk()
        ->assertSee('LGU INTERVENTION LIST')
        ->assertSeeInOrder(['Maria Santos', 'RSBSA-0198', 'Samoki', 'Complete Fertilizer', 'Registered'])
        ->assertSee('Registered (New)')->assertSee('Unregistered (Eligible)')
        ->assertDontSee('Qty / Unit');

    $this->actingAs($this->admin)->get('/interventions/lgu?registration=new')
        ->assertSee('Pedro Reyes')->assertDontSee('Maria Santos')->assertDontSee('Ana Gomez');
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

    $this->post(route('intervention-records.claim', seededRecord('Pedro', 'Reyes')))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'Validate eligibility first.']);
    $this->post(route('intervention-records.validate', $carlos), ['validation_status' => ['eligible']])
        ->assertSessionHasErrorsIn('intervention', 'validation_status');
});

it('lists pending records in the validation queue', function () {
    $this->actingAs($this->agritech)->get('/validation')->assertOk()
        ->assertSee('BENEFICIARY VALIDATION')
        ->assertSee('Carlos Ibanez')->assertSee('Pedro Reyes')
        ->assertSee('DA - Complete Fertilizer (Batch 2026-Q3)')
        ->assertDontSee('Juan Dela Cruz');

    InterventionRecord::where('validation_status', 'pending')->update(['validation_status' => 'eligible']);
    $this->get('/validation')->assertSee('No records are waiting for validation.');
});

it('keeps lists and actions behind their permissions', function () {
    $carlos = seededRecord('Carlos', 'Ibanez');

    $this->actingAs($this->encoder)->get('/interventions/da')->assertForbidden();
    $this->actingAs($this->encoder)->get('/validation')->assertForbidden();
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
