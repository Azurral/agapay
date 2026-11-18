<?php

use App\Http\Controllers\ClaimingController;
use App\Models\AuditLog;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\SignedSheet;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// Panel: "How do they claim? Do they sign? We need evidence" and "a calendar for claiming; after the claiming date has
// passed, all unclaimed need verification to be forwarded to the next claiming date."

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
    Carbon::setTestNow('2026-11-18 09:00:00');
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    $this->q3 = DistributionCycle::where('code', '2026-Q3')->sole();   // claiming on Aug 14, 2026: over
    $this->q4 = DistributionCycle::create(['code' => '2026-Q4', 'label' => '2026-Q4 Wet Season', 'schedule_date' => '2026-11-25',
        'claim_end_date' => '2026-11-27', 'venue' => 'Bontoc Municipal Gym', 'status' => DistributionCycle::STATUS_SCHEDULED]);
});

afterEach(fn () => Carbon::setTestNow());

function q3Record(string $first): InterventionRecord
{
    return InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where('first_name', $first))
        ->where('distribution_cycle_id', DistributionCycle::where('code', '2026-Q3')->value('id'))->sole();
}

it('keeps a claiming period on each cycle', function () {
    $this->actingAs($this->admin)->put(route('cycles.update', $this->q4), [
        'code' => '2026-Q4', 'label' => '2026-Q4 Wet Season', 'schedule_date' => '2026-11-25', 'claim_end_date' => '2026-11-24',
        'venue' => 'Bontoc Municipal Gym', 'status' => 'scheduled',
    ])->assertSessionHasErrors(['claim_end_date' => 'The claiming period cannot end before it starts.']);

    expect($this->q4->claimingEnded())->toBeFalse()
        ->and($this->q3->claimingEnded())->toBeTrue()       // no end date: the schedule date only
        ->and($this->q4->claimingDays())->toBe(['2026-11-25', '2026-11-26', '2026-11-27']);
});

it('shows the claiming days on a calendar for every role', function (string $who) {
    $this->actingAs($this->{$who})->get('/claiming?month=2026-11')->assertOk()
        ->assertSee('Claiming Calendar')
        ->assertSee('November 2026')
        ->assertSee('2026-Q4 Wet Season')
        ->assertSee('Bontoc Municipal Gym')
        ->assertSee(route('claiming.show', $this->q4), false);
})->with(['admin', 'agritech', 'encoder']);

it('lists unclaimed records for verification once the claiming period is over', function () {
    $this->actingAs($this->agritech)->get(route('claiming.show', $this->q3))->assertOk()
        ->assertSee('For verification')
        ->assertSeeInOrder(['Carlos Ibanez', 'Complete Fertilizer'])
        ->assertSeeInOrder(['Pedro Reyes', 'Emergency Seedlings'])
        ->assertDontSee('Maria Santos');   // Bedridden: not claimable, nothing to forward

    $this->get(route('claiming.show', $this->q4))->assertOk()->assertDontSee('For verification');   // not over yet
});

it('forwards a verified unclaimed record to the next claiming date', function () {
    $carlos = q3Record('Carlos');

    $this->actingAs($this->agritech)->post(route('claiming.forward', $carlos))
        ->assertRedirect()->assertSessionHas('status', 'Carlos Ibanez: Complete Fertilizer forwarded to 2026-Q4 (claiming Nov 25, 2026).');

    $carlos->refresh();
    $moved = InterventionRecord::where(['beneficiary_id' => $carlos->beneficiary_id, 'distribution_cycle_id' => $this->q4->id])->sole();

    expect($carlos->forward_status)->toBe('forwarded')
        ->and($carlos->forwarded_record_id)->toBe($moved->id)
        ->and($moved->claim_status)->toBe('unclaimed')->and($moved->validation_status)->toBe('eligible')
        ->and(AuditLog::where('action', 'Forwarded Unclaimed Record')->exists())->toBeTrue();

    // The old record is history now: it cannot be forwarded or claimed again.
    $this->post(route('claiming.forward', $carlos))->assertSessionHasErrors();
    $this->actingAs($this->admin)->post(route('intervention-records.claim', $carlos), ['quantity' => 1])->assertSessionHasErrorsIn('intervention');
});

it('closes an unclaimed record without forwarding it, with a reason', function () {
    $pedro = q3Record('Pedro');

    $this->actingAs($this->agritech)->post(route('claiming.close', $pedro), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('claiming.close', $pedro), ['reason' => 'Moved to Baguio; confirmed by the barangay'])->assertSessionHasNoErrors();

    expect($pedro->fresh())->forward_status->toBe('not_forwarded')->forward_note->toBe('Moved to Baguio; confirmed by the barangay');
});

it('needs a later cycle to forward to', function () {
    $this->q4->delete();

    $this->actingAs($this->agritech)->post(route('claiming.forward', q3Record('Carlos')))
        ->assertSessionHasErrors(['forward' => 'Add the next distribution cycle first; there is no later claiming date.']);
});

it('lets only those who validate records decide on unclaimed ones', function () {
    $this->actingAs($this->encoder)->post(route('claiming.forward', q3Record('Carlos')))->assertForbidden();
});

it('prints an acknowledgment sheet with a signature line for each farmer', function () {
    $response = $this->actingAs($this->agritech)->get(route('claiming.sheet', [$this->q3, 'intervention' => q3Record('Carlos')->intervention_id]));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    $html = view('claiming.sheet', app(ClaimingController::class)->sheetData($this->q3, q3Record('Carlos')->intervention))->render();
    expect($html)->toContain('ACKNOWLEDGMENT RECEIPT')
        ->toContain('Carlos Ibanez')->toContain('RSBSA-0099')
        ->toContain('Signature / Thumbmark')
        ->toContain('Noted by')->toContain('Municipal Agriculturist');
});

it('keeps the signed sheet as proof of the release', function () {
    $carlos = q3Record('Carlos');

    $this->actingAs($this->agritech)->post(route('claiming.signed', $this->q3), [
        'intervention_id' => $carlos->intervention_id,
        'file' => UploadedFile::fake()->create('signed.pdf', 200, 'application/pdf'),
    ])->assertRedirect()->assertSessionHas('status', 'Signed sheet saved for DA - Complete Fertilizer.');

    $sheet = SignedSheet::sole();
    expect(Storage::disk('local')->exists($sheet->path))->toBeTrue()
        ->and(AuditLog::where('action', 'Uploaded Signed Sheet')->exists())->toBeTrue();

    $this->get(route('claiming.show', $this->q3))->assertSee('signed.pdf');
    $this->get(route('claiming.signed.download', $sheet))->assertOk();
});
