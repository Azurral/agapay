<?php

use App\Exceptions\InterventionRuleViolation;
use App\Models\AssistanceRequest;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\EligibilityCheck;
use App\Services\InterventionAssignment;
use App\Support\Navigation;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InterventionSeeder;

/** Labels of the checks that did not pass. */
function flags(InterventionRecord $record): array
{
    return collect(app(EligibilityCheck::class)->run($record->fresh()))->reject(fn ($c) => $c['ok'])->pluck('label')->values()->all();
}

describe('the verification rules', function () {
    beforeEach(function () {
        seedRoles();
        $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
        $this->admin = userWithRole(Role::ADMIN);
        $this->agritech = userWithRole(Role::AGRITECH);
        $this->encoder = userWithRole(Role::ENCODER);
        $this->claims = app(ClaimService::class);
        $this->q3 = DistributionCycle::where('code', '2026-Q3')->sole();
        $this->q2 = DistributionCycle::where('code', '2026-Q2')->sole();
        $this->assign = fn (Beneficiary $b, string $source, string $program, ?DistributionCycle $cycle = null) => app(InterventionAssignment::class)
            ->assign($b, program($source, $program), $cycle ?? $this->q3, ['quantity' => 1], $this->encoder);
    });

    it('starts new program records For Verification and refuses to release them until verified', function () {
        [$juan] = sameHousehold(['Juan']);
        $record = ($this->assign)($juan, 'lgu', 'Emergency Seedlings');

        expect($record->validation_status)->toBe('pending')
            ->and(InterventionRecord::validationLabel('pending'))->toBe('For Verification')
            ->and(fn () => $this->claims->claim($record, $this->admin))->toThrow(InterventionRuleViolation::class, 'Verify eligibility first.');

        $this->claims->validate($record, 'eligible', $this->agritech);
        expect($this->claims->claim($record->fresh(), $this->admin)->claim_status)->toBe('claimed');
    });

    it('still records a past distribution from a logbook without a separate verification', function () {
        [$juan] = sameHousehold(['Juan']);
        $record = ($this->assign)($juan, 'lgu', 'Emergency Seedlings');

        $claimed = $this->claims->claim($record, $this->encoder, ['date_distributed' => '2026-07-01'], historical: true);

        expect($claimed)->validation_status->toBe('eligible')->claim_status->toBe('claimed');
    });

    it('passes every automatic check for an ordinary farmer', function () {
        [$juan] = sameHousehold(['Juan']);

        expect(flags(($this->assign)($juan, 'da', 'Certified Rice Seeds')))->toBe([]);
    });

    it('flags an applicant under 18', function () {
        [$juan] = sameHousehold(['Juan']);
        $record = ($this->assign)($juan, 'lgu', 'Emergency Seedlings');
        $juan->forceFill(['birthdate' => now()->subYears(17)->toDateString()])->saveQuietly();

        expect(flags($record))->toBe(['18 years old or older']);
    });

    it('flags a DA record whose RSBSA No. was removed, but not an LGU one', function () {
        [$juan] = sameHousehold(['Juan']);
        $da = ($this->assign)($juan, 'da', 'Certified Rice Seeds');
        $lgu = ($this->assign)($juan, 'lgu', 'Emergency Seedlings');
        $juan->forceFill(['rsbsa_number' => null])->saveQuietly();

        expect(flags($da))->toBe(['RSBSA No. on file (needed for DA programs)'])
            ->and(flags($lgu))->toBe([]);
    });

    it('flags a possible duplicate profile with the same name and birthdate', function () {
        [$juan] = sameHousehold(['Juan']);
        Beneficiary::factory()->create(['first_name' => 'JUAN', 'middle_name' => null, 'last_name' => 'dela cruz', 'birthdate' => $juan->birthdate, 'barangay_id' => brgy('Samoki'), 'address' => 'Purok 9']);

        expect(flags(($this->assign)($juan, 'lgu', 'Emergency Seedlings')))->toBe(['No other profile with the same name and birthdate']);
    });

    it('flags a household that already received the program this cycle', function () {
        [$juan, $maria] = sameHousehold(['Juan', 'Maria']);
        $this->claims->claim(record($juan, program('da', 'PAFF'), ['validation_status' => 'eligible']), $this->admin);

        expect(flags(($this->assign)($maria, 'da', 'PAFF')))->toBe(['Household has not received this program this cycle']);
    });

    it('flags someone tagged Deceased, Inactive or Relocated in an earlier record', function (string $tag) {
        [$juan] = sameHousehold(['Juan']);
        record($juan, program('da', 'PAFF'), ['validation_status' => $tag, 'distribution_cycle_id' => $this->q2->id]);

        expect(flags(($this->assign)($juan, 'lgu', 'Emergency Seedlings')))->toBe(['Not tagged Deceased, Inactive or Relocated before']);
    })->with(['deceased', 'inactive', 'relocated']);

    it('flags a repeat of LGU assistance already received in another cycle', function () {
        [$juan] = sameHousehold(['Juan']);
        $first = ($this->assign)($juan, 'lgu', 'Municipal Cash Subsidy', $this->q2);
        $this->claims->validate($first, 'eligible', $this->agritech);
        $this->claims->claim($first->fresh(), $this->admin);
        $repeat = record($juan, program('lgu', 'Municipal Cash Subsidy'), ['validation_status' => 'pending']);

        expect(flags($repeat))->toBe(['Not a repeat of LGU assistance already received']);
    });
});

describe('the verification page', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'Admin_01')->sole();
        $this->agritech = User::where('username', 'Agritech_02')->sole();
        $this->encoder = User::where('username', 'Encoder_03')->sole();
    });

    it('is in the menu of the administrator and the agricultural technologist only', function () {
        expect(collect(Navigation::items($this->agritech))->pluck('label'))->toContain('Eligibility Verification')
            ->and(collect(Navigation::items($this->admin))->pluck('label'))->toContain('Eligibility Verification')
            ->and(collect(Navigation::items($this->encoder))->pluck('label'))->not->toContain('Eligibility Verification');

        $this->actingAs($this->encoder)->get('/verification')->assertForbidden();
    });

    it('lists the records waiting for verification with their automatic checks', function () {
        $pending = InterventionRecord::where('validation_status', 'pending')->with('beneficiary')->get();
        expect($pending)->not->toBeEmpty();

        $response = $this->actingAs($this->agritech)->get('/verification')->assertOk()
            ->assertSee('ELIGIBILITY VERIFICATION')->assertSee('Automatic checks');
        foreach ($pending as $record) {
            $response->assertSee($record->beneficiary->fullName());
        }
    });

    it('confirms every record whose checks all passed, leaving flagged ones for a person to decide', function () {
        $pending = InterventionRecord::where('validation_status', 'pending')->get();
        [$passed, $flagged] = $pending->partition(fn ($r) => app(EligibilityCheck::class)->passes($r));
        expect($passed)->not->toBeEmpty();

        $this->actingAs($this->agritech)->post('/verification/confirm-passed')
            ->assertRedirect()->assertSessionHas('status', "{$passed->count()} ".str('record')->plural($passed->count()).' verified as Eligible.');

        expect(InterventionRecord::whereIn('id', $passed->modelKeys())->pluck('validation_status')->unique()->all())->toBe(['eligible'])
            ->and(InterventionRecord::whereIn('id', $flagged->modelKeys())->pluck('validation_status')->unique()->all())->toBe($flagged->isEmpty() ? [] : ['pending'])
            ->and(AuditLog::where('action', 'Validated Intervention Record')->count())->toBe($passed->count());
    });

    it('lets the agricultural technologist verify from the beneficiary profile with the checklist', function () {
        $record = InterventionRecord::where('validation_status', 'pending')->with('beneficiary')->first();

        $this->actingAs($this->agritech)->get(route('beneficiaries.show', $record->beneficiary))->assertOk()
            ->assertSee('ELIGIBILITY VERIFICATION')->assertSee('Verify Eligibility')->assertSee('18 years old or older');
    });

    it('sends an approved assistance request to verification first', function () {
        $request = AssistanceRequest::where('status', AssistanceRequest::PENDING)
            ->whereHas('intervention', fn ($q) => $q->where('source', 'lgu'))->first();

        $this->actingAs($this->admin)->post(route('assistance-requests.approve', $request))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'waiting for eligibility verification'));

        expect($request->fresh()->record->validation_status)->toBe('pending');
    });
});
