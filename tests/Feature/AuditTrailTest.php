<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $this->encoder = userWithRole(Role::ENCODER, ['username' => 'Encoder_03']);
    AuditLog::query()->toBase()->delete(); // start from an empty trail (bypasses the append-only guard)

    Carbon::setTestNow('2026-07-17 09:12:00');
    AuditLogger::record('Added User', label: 'Encoder_04', actor: $this->admin);
    Carbon::setTestNow('2026-07-16 15:45:00');
    AuditLogger::record('Updated Beneficiary Profile', label: 'Juan Dela Cruz (RSBSA-0231)', actor: $this->encoder);
    Carbon::setTestNow();
});

it('is only for users with audit.view', function () {
    $this->get('/audit-trail')->assertRedirect(route('login')); // guest (before actingAs, which persists)
    $this->actingAs($this->encoder)->get('/audit-trail')->assertForbidden();
});

it('lists entries newest first in the Figma format', function () {
    $this->actingAs($this->admin)->get('/audit-trail')
        ->assertOk()
        ->assertSee('AUDIT TRAIL')
        ->assertSeeInOrder([
            'Jul 17, 2026 - 9:12 AM', 'Admin_01', 'Added User', 'Encoder_04',
            'Jul 16, 2026 - 3:45 PM', 'Encoder_03', 'Updated Beneficiary Profile', 'Juan Dela Cruz (RSBSA-0231)',
        ]);
});

it('filters by user', function () {
    $this->actingAs($this->admin)->get('/audit-trail?user=encoder')
        ->assertSee('Updated Beneficiary Profile')
        ->assertDontSee('Added User</td>', false);
});

it('filters by action', function () {
    $this->actingAs($this->admin)->get('/audit-trail?action=Added+User')
        ->assertSee('Encoder_04')
        ->assertDontSee('Juan Dela Cruz (RSBSA-0231)');
});

it('filters by date typed in plain words', function () {
    $this->actingAs($this->admin)->get('/audit-trail?timestamp=Jul+16,+2026')
        ->assertSee('Juan Dela Cruz (RSBSA-0231)')
        ->assertDontSee('>Encoder_04<', false);
});

it('explains an unreadable date instead of failing', function () {
    $this->actingAs($this->admin)->get('/audit-trail?timestamp=not-a-date')
        ->assertOk()
        ->assertSee('Enter a date such as Jul 17, 2026.')
        ->assertDontSee('Juan Dela Cruz (RSBSA-0231)');
});
