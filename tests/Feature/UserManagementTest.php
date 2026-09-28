<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $this->roleId = fn (string $slug) => Role::where('slug', $slug)->value('id');
});

it('is only for users with users.manage', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/users')->assertForbidden();
    $this->actingAs(userWithRole(Role::AGRITECH))->post('/users', [])->assertForbidden();
});

it('lists users with role and status like Figma', function () {
    userWithRole(null, ['username' => 'Encoder_04', 'status' => User::STATUS_INACTIVE]);

    $this->actingAs($this->admin)->get('/users')
        ->assertOk()
        ->assertSee('USER MANAGEMENT')
        ->assertSeeInOrder(['Admin_01', 'Administrator', 'Active', 'Encoder_04', 'No Role', 'Inactive'])
        ->assertSee('Configure Roles');
});

it('searches and filters by role', function () {
    userWithRole(Role::ENCODER, ['username' => 'Encoder_03']);
    userWithRole(null, ['username' => 'Encoder_04']);

    $this->actingAs($this->admin)->get('/users?q=enc&role=none')
        ->assertSee('Encoder_04')
        ->assertDontSee('Encoder_03');
});

it('opens the Add User modal from the quick action', function () {
    $this->actingAs($this->admin)->get('/users?add=1')->assertSee('x-data="{ open: true }"', false);
});

it('adds a user', function () {
    $this->actingAs($this->admin)->post('/users', [
        'name' => 'Rosa Encoder',
        'username' => 'Encoder_05',
        'password' => 'strong-pass-1',
        'password_confirmation' => 'strong-pass-1',
        'role_id' => ($this->roleId)(Role::ENCODER),
        'status' => User::STATUS_ACTIVE,
    ])->assertRedirect(route('users.index'))->assertSessionHas('status', 'Encoder_05 was added.');

    $user = User::firstWhere('username', 'Encoder_05');
    expect(Hash::check('strong-pass-1', $user->password))->toBeTrue()
        ->and(AuditLog::where('action', 'Added User')->where('record_label', 'Encoder_05')->exists())->toBeTrue();
});

it('rejects duplicate usernames regardless of case and weak passwords', function () {
    $this->actingAs($this->admin)->post('/users', [
        'name' => 'Dup', 'username' => 'admin_01', 'password' => 'short', 'password_confirmation' => 'short',
        'role_id' => null, 'status' => User::STATUS_ACTIVE,
    ])->assertSessionHasErrorsIn('createUser', ['username', 'password']);
});

it('edits a user and keeps the password when left blank', function () {
    $user = userWithRole(Role::ENCODER, ['username' => 'Encoder_03', 'password' => 'keep-this-pass']);

    $this->actingAs($this->admin)->put("/users/{$user->id}", [
        'name' => $user->name, 'username' => 'Encoder_03', 'password' => '', 'password_confirmation' => '',
        'role_id' => ($this->roleId)(Role::AGRITECH), 'status' => User::STATUS_INACTIVE,
    ])->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->role->slug)->toBe(Role::AGRITECH)
        ->and($user->status)->toBe(User::STATUS_INACTIVE)
        ->and(Hash::check('keep-this-pass', $user->password))->toBeTrue();
});

it('stops admins from locking themselves out', function (array $change) {
    $this->actingAs($this->admin)->put("/users/{$this->admin->id}", [
        'name' => $this->admin->name, 'username' => 'Admin_01', 'password' => '', 'password_confirmation' => '',
        'role_id' => ($this->roleId)(Role::ADMIN), 'status' => User::STATUS_ACTIVE, ...$change,
    ])->assertSessionHasErrorsIn('editUser', ['role_id' => 'You cannot deactivate or change the role of your own account.']);

    expect($this->admin->fresh()->canSignIn())->toBeTrue();
})->with([
    'deactivate self' => [['status' => User::STATUS_INACTIVE]],
    'demote self' => [fn () => ['role_id' => Role::where('slug', Role::ENCODER)->value('id')]],
]);

it('configures role permissions', function () {
    $agritech = Role::firstWhere('slug', Role::AGRITECH);
    $matrix = Role::with('permissions')->get()->mapWithKeys(
        fn (Role $role) => [$role->id => $role->permissions->pluck('slug')->all()]
    )->all();
    $matrix[$agritech->id][] = 'inventory.view';

    $this->actingAs($this->admin)->put('/roles/permissions', ['permissions' => $matrix])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('status', 'Role permissions saved.');

    expect($agritech->fresh()->permissions->pluck('slug'))->toContain('inventory.view')
        ->and(AuditLog::where('action', 'Configured Roles')->where('record_label', 'Agricultural Technologist')->exists())->toBeTrue();
});

it('never removes the admin lock-out permissions', function () {
    $admin = Role::firstWhere('slug', Role::ADMIN);

    $this->actingAs($this->admin)->put('/roles/permissions', ['permissions' => [$admin->id => ['dashboard.view']]]);

    expect($admin->fresh()->permissions->pluck('slug')->all())
        ->toContain('users.manage', 'roles.configure', 'dashboard.view');
});

it('ignores unknown permission slugs and role ids', function () {
    $this->actingAs($this->admin)->put('/roles/permissions', ['permissions' => [99999 => ['users.manage'], ($this->roleId)(Role::ENCODER) => ['hack.everything']]])
        ->assertRedirect(route('users.index'));

    expect(Permission::where('slug', 'hack.everything')->exists())->toBeFalse()
        ->and(Role::firstWhere('slug', Role::ENCODER)->permissions->pluck('slug')->all())->toBe(['dashboard.view']);
});

it('limits Configure Roles to roles.configure holders', function () {
    $admin = Role::firstWhere('slug', Role::ADMIN);
    $admin->permissions()->detach(Permission::where('slug', 'roles.configure')->value('id'));

    $this->actingAs($this->admin->fresh())->put('/roles/permissions', ['permissions' => []])->assertForbidden();
});

it('never lets a role lose its home dashboard', function () {
    $encoder = Role::firstWhere('slug', Role::ENCODER);

    $this->actingAs($this->admin)->put('/roles/permissions', ['permissions' => [$encoder->id => ['import.run']]]);

    expect($encoder->fresh()->permissions->pluck('slug')->all())->toContain('dashboard.view', 'import.run');
});

it('does not crash on array query parameters', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertOk();
})->with([
    '/users?q[]=a&role[]=b',
    '/audit-trail?timestamp[]=x&user[]=y&action[]=z',
]);
