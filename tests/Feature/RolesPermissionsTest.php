<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\UserSeeder;

beforeEach(fn () => seedRoles());

it('seeds three roles and every catalog permission', function () {
    expect(Role::pluck('slug')->all())
        ->toEqualCanonicalizing([Role::ADMIN, Role::AGRITECH, Role::ENCODER])
        ->and(Permission::count())->toBe(count(PermissionCatalog::PERMISSIONS));
});

it('is idempotent', function () {
    seedRoles();

    expect(Role::count())->toBe(3)
        ->and(Permission::count())->toBe(count(PermissionCatalog::PERMISSIONS));
});

it('gives each role its default permissions', function () {
    $admin = userWithRole(Role::ADMIN);
    $agritech = userWithRole(Role::AGRITECH);
    $encoder = userWithRole(Role::ENCODER);

    expect($admin->hasPermission('users.manage'))->toBeTrue()
        ->and($admin->hasPermission('audit.view'))->toBeTrue()
        ->and($agritech->hasPermission('interventions.validate'))->toBeTrue()
        ->and($agritech->hasPermission('users.manage'))->toBeFalse()
        ->and($encoder->hasPermission('import.run'))->toBeTrue()
        ->and($encoder->hasPermission('audit.view'))->toBeFalse();
});

it('denies every permission to inactive and role-less users', function () {
    $inactive = userWithRole(Role::ADMIN, ['status' => User::STATUS_INACTIVE]);
    $noRole = userWithRole(null);

    expect($inactive->hasPermission('dashboard.view'))->toBeFalse()
        ->and($inactive->canSignIn())->toBeFalse()
        ->and($noRole->hasPermission('dashboard.view'))->toBeFalse()
        ->and($noRole->roleName())->toBe('No Role');
});

it('exposes permissions as gate abilities', function () {
    $admin = userWithRole(Role::ADMIN);
    $encoder = userWithRole(Role::ENCODER);

    expect($admin->can('users.manage'))->toBeTrue()
        ->and($encoder->can('users.manage'))->toBeFalse()
        ->and($admin->can('not.a.permission'))->toBeFalse();
});

it('labels users by role', function () {
    $agritech = userWithRole(Role::AGRITECH);

    expect($agritech->roleName())->toBe('Agricultural Technologist')
        ->and($agritech->roleShortName())->toBe('Agricultural Tech')
        ->and($agritech->greetingName())->toBe('Agritech')
        ->and($agritech->avatarColor())->toBe('#4671ff');
});

it('gives every user an initials avatar in their role colour', function (?string $role, string $name, string $initials, string $color) {
    $user = userWithRole($role, ['name' => $name, 'username' => 'Some_User']);

    expect($user->initials())->toBe($initials)->and($user->avatarColor())->toBe($color);
})->with([
    'two names' => [Role::ADMIN, 'Maria Santos', 'MS', '#8037ff'],
    'middle names' => [Role::AGRITECH, 'Juan dela Cruz', 'JC', '#4671ff'],
    'one name' => [Role::ENCODER, 'Encoder', 'EN', '#0bcaff'],
    'spaces and case' => [Role::ENCODER, '  ana   gomez ', 'AG', '#0bcaff'],
    'no role' => [null, 'Lito Bayang', 'LB', '#9ca3af'],
]);

it('falls back to the username for initials', function () {
    expect(userWithRole(Role::ENCODER, ['name' => '', 'username' => 'encoder_05'])->initials())->toBe('EN');
});

it('seeds the four Figma accounts', function () {
    $this->seed(UserSeeder::class);

    expect(User::pluck('username')->all())
        ->toEqualCanonicalizing(['Admin_01', 'Agritech_02', 'Encoder_03', 'Encoder_04'])
        ->and(User::firstWhere('username', 'Encoder_04'))
        ->status->toBe(User::STATUS_INACTIVE)
        ->role_id->toBeNull();
});
