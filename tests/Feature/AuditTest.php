<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

beforeEach(fn () => seedRoles());

it('logs account creation without secrets', function () {
    $actor = userWithRole(Role::ADMIN);
    $this->actingAs($actor);

    $created = userWithRole(Role::ENCODER, ['username' => 'Encoder_04']);
    $log = AuditLog::where('action', 'Added User')->where('auditable_id', $created->id)->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->record_label)->toBe('Encoder_04')
        ->and($log->new_values)->toHaveKey('username', 'Encoder_04')
        ->and($log->new_values)->not->toHaveKeys(['password', 'remember_token']);
});

it('logs updates with old and new values', function () {
    $user = userWithRole(Role::ENCODER);
    $agritechId = Role::where('slug', Role::AGRITECH)->value('id');

    $user->update(['role_id' => $agritechId]);
    $log = AuditLog::where('action', 'Updated User')->sole();

    expect($log->old_values)->toBe(['role_id' => Role::where('slug', Role::ENCODER)->value('id')])
        ->and($log->new_values)->toBe(['role_id' => $agritechId]);
});

it('logs password changes without recording the password', function () {
    $user = userWithRole(Role::ENCODER);
    $user->update(['password' => 'a-new-password']);

    $log = AuditLog::where('action', 'Updated User')->sole();

    expect($log->old_values)->toBeNull()->and($log->new_values)->toBeNull();
});

it('skips noise-only updates', function () {
    $user = userWithRole(Role::ENCODER);
    $user->update(['remember_token' => 'abc', 'last_login_at' => now()]);

    expect(AuditLog::where('action', 'Updated User')->count())->toBe(0);
});

it('is append-only', function () {
    $user = userWithRole(Role::ENCODER);
    $log = AuditLog::firstOrFail();

    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('logs sign-in and sign-out', function () {
    $user = userWithRole(Role::ADMIN, ['username' => 'Admin_01', 'password' => 'secret-pass']);

    $this->post('/login', ['username' => 'Admin_01', 'password' => 'secret-pass']);
    $this->post('/logout');

    expect(AuditLog::where('user_id', $user->id)->pluck('action')->all())
        ->toContain('Logged In', 'Logged Out');
});
