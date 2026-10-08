<?php

use App\Models\AuditLog;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Services\InterventionAssignment;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->carlos = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Carlos'))->sole();
});

it('lists the seeded archived records under Archived/Restore', function () {
    $this->actingAs($this->admin)->get('/interventions/da/archived')->assertOk()
        ->assertSee('DA INTERVENTION LIST')
        ->assertSee('DA Intervention - Recently Deleted')
        ->assertSeeInOrder(['Federico Wasing', 'N/A', 'Deceased - confirmed by barangay', 'Jul 8, 2026', 'Agritech_02', 'Restore'])
        ->assertSeeInOrder(['Estrella Domogen', 'Data correction - re-entered under new RSBSA no.', 'Jul 3, 2026', 'Encoder_03'])
        ->assertDontSee('Lorna Reyes');   // LGU

    $this->actingAs($this->admin)->get('/interventions/lgu/archived')
        ->assertSee('LGU Intervention - Recently Deleted')->assertSee('Lorna Reyes')->assertSee('Teresa Ibanez');

    $this->actingAs($this->admin)->get('/interventions/da/archived?q=federico')->assertSee('Federico Wasing')->assertDontSee('Estrella Domogen');
});

it('archives a record with a reason and lists it under Archived/Restore', function () {
    $this->actingAs($this->admin)->from('/interventions/da')
        ->post(route('intervention-records.archive', $this->carlos), ['reason' => 'Encoded under the wrong program'])
        ->assertRedirect('/interventions/da')->assertSessionHasNoErrors();

    expect(InterventionRecord::find($this->carlos->id))->toBeNull()
        ->and(InterventionRecord::onlyTrashed()->find($this->carlos->id))->delete_reason->toBe('Encoded under the wrong program')
        ->and(AuditLog::where('action', 'Archived Intervention Record')->value('record_label'))->toBe('Carlos Ibanez - Complete Fertilizer (2026-Q3)');

    $this->get('/interventions/da')->assertDontSee('RSBSA-0099');   // the flash message still names him
    $this->get('/interventions/da/archived')->assertSeeInOrder(['Carlos Ibanez', 'Encoded under the wrong program', now()->format('M j, Y'), 'Admin_01']);
});

it('requires a reason to archive', function () {
    $this->actingAs($this->admin)->post(route('intervention-records.archive', $this->carlos), ['reason' => ' '])
        ->assertSessionHasErrorsIn('intervention', ['reason' => 'Enter a reason.']);

    expect(InterventionRecord::find($this->carlos->id))->not->toBeNull();
});

it('restores an archived record', function () {
    $federico = InterventionRecord::onlyTrashed()->whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Federico'))->sole();

    $this->actingAs($this->admin)->from('/interventions/da/archived')
        ->post(route('intervention-records.restore', $federico))->assertRedirect('/interventions/da/archived')->assertSessionHasNoErrors();

    expect($federico->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::where('action', 'Restored Intervention Record')->exists())->toBeTrue();
    $this->get('/interventions/da')->assertSee('Federico Wasing');
});

it('refuses to restore into a taken slot', function () {
    $federico = InterventionRecord::onlyTrashed()->whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Federico'))->sole();
    $federico->beneficiary->update(['rsbsa_number' => 'RSBSA-0777']);   // DA programs need a number
    app(InterventionAssignment::class)->assign($federico->beneficiary, $federico->intervention, $federico->cycle, [], $this->admin);

    $this->actingAs($this->admin)->post(route('intervention-records.restore', $federico))
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'An active record already exists for this intervention and cycle.']);
    expect($federico->fresh()->trashed())->toBeTrue();
});

it('shows the archive modal with the records on the page', function () {
    $this->actingAs($this->admin)->get('/interventions/da')
        ->assertSee('Archive Intervention Record')
        ->assertSee('Carlos Ibanez - Complete Fertilizer (2026-Q3)')
        ->assertSee('name="reason"', false);
});

it('keeps archive and restore to holders of interventions.archive', function () {
    $federico = InterventionRecord::onlyTrashed()->first();

    $this->actingAs($this->agritech)->get('/interventions/da/archived')->assertForbidden();
    $this->actingAs($this->agritech)->post(route('intervention-records.archive', $this->carlos), ['reason' => 'x'])->assertForbidden();
    $this->actingAs($this->agritech)->post(route('intervention-records.restore', $federico))->assertForbidden();
});

it('404s an unknown archived source', function () {
    $this->actingAs($this->admin)->get('/interventions/xyz/archived')->assertNotFound();
});
