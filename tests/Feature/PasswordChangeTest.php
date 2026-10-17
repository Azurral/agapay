<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $this->encoder = userWithRole(Role::ENCODER, ['username' => 'Encoder_03', 'password' => 'old-pass-123']);
});

function changePassword(array $overrides = []): array
{
    return ['current_password' => 'old-pass-123', 'password' => 'new-pass-456', 'password_confirmation' => 'new-pass-456', ...$overrides];
}

it('offers every user a Change password link in the account menu', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/dashboard')->assertOk()
        ->assertSee(route('password.edit'), false)->assertSee('Change password');
})->with([Role::ADMIN, Role::AGRITECH, Role::ENCODER]);

it('lets a user change their own password', function () {
    $this->actingAs($this->encoder)->get(route('password.edit'))->assertOk()
        ->assertSee('CHANGE PASSWORD')->assertSee('Current Password')->assertSee('New Password');

    $this->put(route('password.update'), changePassword())
        ->assertRedirect(route('dashboard'))->assertSessionHas('status', 'Your password was changed.');

    expect(Hash::check('new-pass-456', $this->encoder->fresh()->password))->toBeTrue()
        ->and(AuditLog::where('action', 'Changed Own Password')->where('user_id', $this->encoder->id)->exists())->toBeTrue()
        ->and(json_encode(AuditLog::latest('id')->first()->new_values))->not->toContain('new-pass');
});

it('refuses a wrong current password, a weak, reused or mistyped new one', function (array $input, string $field, string $message) {
    $this->actingAs($this->encoder)->from(route('password.edit'))->put(route('password.update'), changePassword($input))
        ->assertRedirect(route('password.edit'))->assertSessionHasErrors([$field => $message]);

    expect(Hash::check('old-pass-123', $this->encoder->fresh()->password))->toBeTrue();
})->with([
    'wrong current' => [['current_password' => 'nope-nope-1'], 'current_password', 'That is not your current password.'],
    'too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password', 'The new password must be at least 8 characters.'],
    'same as now' => [['password' => 'old-pass-123', 'password_confirmation' => 'old-pass-123'], 'password', 'Choose a password different from your current one.'],
    'mistyped' => [['password_confirmation' => 'new-pass-457'], 'password', 'The two new passwords do not match.'],
]);

it('makes a user the administrator added change the password at first sign-in', function () {
    $this->actingAs($this->admin)->post('/users', [
        'last_name' => 'Lang', 'first_name' => 'Nora', 'username' => 'Encoder_09', 'password' => 'given-pass-1', 'password_confirmation' => 'given-pass-1',
        'role_id' => Role::where('slug', Role::ENCODER)->value('id'), 'status' => User::STATUS_ACTIVE,
    ]);
    $nora = User::where('username', 'Encoder_09')->sole();
    expect($nora->must_change_password)->toBeTrue();

    $this->actingAs($nora)->get('/dashboard')->assertRedirect(route('password.edit'));
    $this->get(route('password.edit'))->assertOk()->assertSee('Please choose your own password before you continue.');

    $this->put(route('password.update'), changePassword(['current_password' => 'given-pass-1']))->assertRedirect(route('dashboard'));
    expect($nora->fresh()->must_change_password)->toBeFalse();
    $this->get('/dashboard')->assertOk();
});

it('asks again after the administrator resets a password, but not after other edits', function () {
    $edit = fn (array $input) => $this->actingAs($this->admin)->put(route('users.update', $this->encoder), [
        'last_name' => 'Ngalob', 'first_name' => 'Liza', 'username' => 'Encoder_03', 'role_id' => $this->encoder->role_id, 'status' => User::STATUS_ACTIVE,
        'password' => '', 'password_confirmation' => '', ...$input,
    ]);

    $edit(['first_name' => 'Renamed']);
    expect($this->encoder->fresh()->must_change_password)->toBeFalse();

    $edit(['password' => 'reset-pass-1', 'password_confirmation' => 'reset-pass-1']);
    expect($this->encoder->fresh()->must_change_password)->toBeTrue();
});

it('still lets a user who must change the password sign out', function () {
    $this->encoder->forceFill(['must_change_password' => true])->save();

    $this->actingAs($this->encoder)->post(route('logout'))->assertRedirect(route('login'));
});
