<?php

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->person = fn (string $first, string $last) => Beneficiary::where(['first_name' => $first, 'last_name' => $last])->sole();
    $this->juan = ($this->person)('Juan', 'Dela Cruz');
});

it('shows intervention history newest first', function () {
    record($this->juan, program('da', 'Molasses'), [
        'distribution_cycle_id' => DistributionCycle::where('code', '2026-Q2')->value('id'),
        'validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-05-02',
    ]);

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $this->juan))->assertOk()
        ->assertSeeInOrder(['Intervention History', 'Jul 2026', 'DA', 'Certified Rice Seeds', 'Poblacion', 'Claimed', 'May 2026', 'DA', 'Molasses', 'Claimed'])
        ->assertDontSee('No interventions recorded yet.');
});

it('dates unclaimed history rows by the cycle schedule', function () {
    record($this->juan, program('lgu', 'Emergency Seedlings'));

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $this->juan))
        ->assertSeeInOrder(['Aug 2026', 'LGU', 'Emergency Seedlings', 'Unclaimed']);
});

it('reports a household member\'s claim in the banner', function () {
    record(($this->person)('Maria', 'Dela Cruz'), program('da', 'PAFF'), ['validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-07-20']);

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $this->juan))
        ->assertSee('2 other registered members share this address (Maria Dela Cruz, Ana Dela Cruz) - Maria Dela Cruz already claimed PAFF this cycle.');
    $this->actingAs($this->admin)->get(route('beneficiaries.show', ($this->person)('Rosa', 'Mendez')))
        ->assertSee('No other registered members share this address.');
});

it('processes a claim from the admin profile', function () {
    $paff = record($this->juan, program('da', 'PAFF'), ['validation_status' => 'eligible']);

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $this->juan))
        ->assertSee('Process Claim - Juan Dela Cruz')
        ->assertSee('PAFF (2026-Q3)')
        ->assertDontSee('Certified Rice Seeds (2026-Q3)</option>', false)   // already claimed: not offered
        ->assertDontSee('title="No claimable interventions."', false);

    $this->actingAs($this->admin)->from(route('beneficiaries.show', $this->juan))
        ->post(route('intervention-records.claim', $paff), ['date_distributed' => '2026-07-21'])
        ->assertRedirect(route('beneficiaries.show', $this->juan))->assertSessionHasNoErrors();
    expect($paff->fresh()->claim_status)->toBe('claimed');
});

it('asks for proxy fields when the record is deceased', function () {
    $record = record($this->juan, program('da', 'PAFF'), ['validation_status' => 'deceased']);
    $this->actingAs($this->admin);

    $this->get(route('beneficiaries.show', $this->juan))->assertSee('Proxy Claimant')->assertSee('Proof Note');

    $this->post(route('intervention-records.claim', $record))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'A deceased beneficiary can only be claimed by a proxy with a proof note.']);
    $this->post(route('intervention-records.claim', $record), ['proxy_claimant' => 'Maria Dela Cruz', 'proof_note' => 'Death certificate, barangay-certified'])
        ->assertSessionHasNoErrors();
});

it('lets the admin override the household block with a reason', function () {
    record(($this->person)('Maria', 'Dela Cruz'), program('da', 'PAFF'), ['validation_status' => 'eligible', 'claim_status' => 'claimed', 'date_distributed' => '2026-07-20']);
    $record = record($this->juan, program('da', 'PAFF'), ['validation_status' => 'eligible']);
    $this->actingAs($this->admin);

    $this->get(route('beneficiaries.show', $this->juan))->assertSee('Override reason (household already claimed)');
    $this->post(route('intervention-records.claim', $record))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'Maria Dela Cruz already claimed PAFF for this household in 2026-Q3.']);
    $this->post(route('intervention-records.claim', $record), ['override_reason' => 'Separate farm with its own RSBSA'])->assertSessionHasNoErrors();
    expect($record->fresh()->override_reason)->toBe('Separate farm with its own RSBSA');
});

it('verifies eligibility from the agri tech profile', function () {
    $carlos = ($this->person)('Carlos', 'Ibanez');
    $record = $carlos->interventionRecords()->sole();

    $this->actingAs($this->agritech)->get(route('beneficiaries.show', $carlos))->assertOk()
        ->assertSee('Validate Beneficiary: Carlos Ibanez')
        ->assertSee('RSBSA-0099 - Check all that apply, per DA/barangay cross-check')
        ->assertSeeInOrder(['Eligible', 'OFW', 'Bedridden', 'Deceased', 'Inactive', 'Relocated', 'Duplicate', 'Confirm Validation', 'Cancel'])
        ->assertDontSee('title="No intervention records to verify."', false);

    $this->actingAs($this->agritech)->post(route('intervention-records.validate', $record), ['validation_status' => 'bedridden'])
        ->assertSessionHasNoErrors();
    expect($record->fresh()->validation_status)->toBe('bedridden');
});

it('disables the buttons when there is nothing to process', function () {
    $teresa = ($this->person)('Teresa', 'Ibanez');   // only an archived record

    $this->actingAs($this->admin)->get(route('beneficiaries.show', $teresa))
        ->assertSee('title="No claimable interventions."', false)->assertSee('No interventions recorded yet.');
    $this->actingAs($this->agritech)->get(route('beneficiaries.show', $teresa))
        ->assertSee('title="No intervention records to verify."', false);
});

it('gives the encoder claim dropdowns in history outside the edit form', function () {
    $html = $this->actingAs($this->encoder)->get(route('beneficiaries.show', $this->juan))->assertOk()
        ->assertSee('name="claim_status"', false)->assertSee('EDIT MODE')->getContent();

    // No form may be nested inside the profile edit form.
    $edit = substr($html, strpos($html, 'id="profile-edit"'));
    expect(substr_count(substr($edit, 0, strpos($edit, '</form>')), '<form'))->toBe(0)
        ->and($html)->toContain('form="profile-edit"');
});

it('shows each action result once on the profile', function () {
    $carlos = ($this->person)('Carlos', 'Ibanez');
    $record = $carlos->interventionRecords()->sole();

    $html = $this->actingAs($this->agritech)->from(route('beneficiaries.show', $carlos))
        ->followingRedirects()->post(route('intervention-records.validate', $record), ['validation_status' => 'eligible'])
        ->assertOk()->getContent();

    expect(substr_count($html, 'Carlos Ibanez - Complete Fertilizer (2026-Q3): Eligible.'))->toBe(1);
});
