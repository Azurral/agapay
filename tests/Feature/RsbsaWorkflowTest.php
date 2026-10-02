<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Role;
use Database\Seeders\BarangaySeeder;

beforeEach(function () {
    seedRoles();
    $this->seed(BarangaySeeder::class);
    $this->admin = userWithRole(Role::ADMIN);
});

it('walks an applicant from pending to registered', function () {
    $b = Beneficiary::factory()->create(['rsbsa_status' => Beneficiary::RSBSA_PENDING, 'rsbsa_number' => null]);
    AuditLog::query()->toBase()->delete();
    $this->actingAs($this->admin);

    $this->post(route('rsbsa.transition', [$b, 'validate']))->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('rsbsa.transition', [$b, 'endorse']))->assertSessionHasNoErrors();
    $this->post(route('rsbsa.transition', [$b, 'record-number']), ['rsbsa_number' => 'RSBSA-0500'])->assertSessionHasNoErrors();

    expect($b->fresh())
        ->rsbsa_status->toBe(Beneficiary::RSBSA_REGISTERED)
        ->rsbsa_number->toBe('RSBSA-0500')
        ->and(AuditLog::pluck('action')->all())
        ->toContain('Validated RSBSA Registration', 'Endorsed RSBSA Registration to DA-RFO', 'Recorded RSBSA Number')
        ->and(AuditLog::where('action', 'Updated Beneficiary Profile')->exists())->toBeFalse();
});

it('refuses illegal jumps and leaves the record unchanged', function (string $from, string $action) {
    $b = Beneficiary::factory()->create(['rsbsa_status' => $from, 'rsbsa_number' => null]);

    $this->actingAs($this->admin)
        ->post(route('rsbsa.transition', [$b, $action]), ['rsbsa_number' => 'X-1', 'reason' => 'x'])
        ->assertSessionHasErrorsIn('rsbsa');

    expect($b->fresh()->rsbsa_status)->toBe($from);
})->with([
    ['pending_validation', 'record-number'],
    ['pending_validation', 'endorse'],
    ['rejected', 'validate'],
    ['registered', 'return'],
]);

it('requires a reason to return and flags returned records for the encoder', function () {
    $b = Beneficiary::factory()->create(['rsbsa_status' => Beneficiary::RSBSA_PENDING, 'rsbsa_number' => null]);
    $this->actingAs($this->admin);

    $this->post(route('rsbsa.transition', [$b, 'return']))->assertSessionHasErrorsIn('rsbsa');
    expect($b->fresh()->rsbsa_status)->toBe(Beneficiary::RSBSA_PENDING);

    $this->post(route('rsbsa.transition', [$b, 'return']), ['reason' => 'Awaiting Barangay Confirmation']);
    expect($b->fresh())
        ->rsbsa_status->toBe(Beneficiary::RSBSA_RETURNED)
        ->encoding_issue->toBe('Awaiting Barangay Confirmation');

    $this->post(route('rsbsa.transition', [$b, 'resubmit']));
    expect($b->fresh())
        ->rsbsa_status->toBe(Beneficiary::RSBSA_PENDING)
        ->encoding_issue->toBeNull()
        ->rsbsa_status_reason->toBeNull();
});

it('requires a reason to reject', function () {
    $b = Beneficiary::factory()->create(['rsbsa_status' => Beneficiary::RSBSA_VALIDATED, 'rsbsa_number' => null]);
    $this->actingAs($this->admin);

    $this->post(route('rsbsa.transition', [$b, 'reject']))->assertSessionHasErrorsIn('rsbsa');
    $this->post(route('rsbsa.transition', [$b, 'reject']), ['reason' => 'Falsified barangay certification']);

    expect($b->fresh())
        ->rsbsa_status->toBe(Beneficiary::RSBSA_REJECTED)
        ->rsbsa_status_reason->toBe('Falsified barangay certification')
        ->and(AuditLog::where('action', 'Rejected RSBSA Registration')->exists())->toBeTrue();
});

it('refuses an RSBSA number already in use, ignoring case and spaces', function () {
    Beneficiary::factory()->create(['rsbsa_number' => 'RSBSA-0231']);
    $b = Beneficiary::factory()->create(['rsbsa_status' => Beneficiary::RSBSA_ENDORSED, 'rsbsa_number' => null]);

    $this->actingAs($this->admin)
        ->post(route('rsbsa.transition', [$b, 'record-number']), ['rsbsa_number' => ' rsbsa-0231 '])
        ->assertSessionHasErrorsIn('rsbsa');

    expect($b->fresh())->rsbsa_status->toBe(Beneficiary::RSBSA_ENDORSED)->rsbsa_number->toBeNull();
});

it('only lets rsbsa.process holders change status', function () {
    $b = Beneficiary::factory()->create(['rsbsa_status' => Beneficiary::RSBSA_PENDING, 'rsbsa_number' => null]);

    $this->actingAs(userWithRole(Role::ENCODER))->post(route('rsbsa.transition', [$b, 'validate']))->assertForbidden();
});

it('lists pending applications with actions for processors only', function () {
    Beneficiary::factory()->create(['first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Reyes', 'rsbsa_status' => Beneficiary::RSBSA_PENDING, 'rsbsa_number' => null]);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos', 'rsbsa_status' => Beneficiary::RSBSA_REGISTERED]);

    $this->actingAs($this->admin)->get('/rsbsa/register')
        ->assertSee('Pending RSBSA Applications')
        ->assertSee('Pedro Reyes')->assertSee('Pending Validation')->assertSee('>Validate<', false)
        ->assertDontSee('Maria Santos');

    $this->actingAs(userWithRole(Role::ENCODER))->get('/rsbsa/register')
        ->assertSee('Pedro Reyes')->assertDontSee('>Validate<', false);
});
