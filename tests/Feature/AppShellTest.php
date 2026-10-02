<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\KnownAccounts;
use App\Support\Navigation;

beforeEach(fn () => seedRoles());

it('builds each role\'s Figma navigation in order', function (string $role, array $labels) {
    expect(array_column(Navigation::items(userWithRole($role)), 'label'))->toBe($labels);
})->with([
    'admin' => [Role::ADMIN, ['Home', 'DA Intervention', 'LGU Intervention', 'Newly Registered', 'Disaster Reports', 'User Management', 'Audit Trail', 'Reports']],
    'agritech' => [Role::AGRITECH, ['Home', 'Beneficiary Validation', 'DA Intervention', 'LGU Intervention', 'Disaster Reports', 'Reports']],
    'encoder' => [Role::ENCODER, ['Home', 'Beneficiary Profiles', 'Intervention Records', 'Inventory', 'Reports']],
]);

it('hides nav items whose permission the role lost', function () {
    $admin = userWithRole(Role::ADMIN);
    $admin->role->permissions()->detach(Permission::where('slug', 'audit.view')->value('id'));

    expect(array_column(Navigation::items($admin->fresh()), 'label'))->not->toContain('Audit Trail');
});

it('builds each role\'s quick actions with Figma pill sizes', function (string $role, array $labels, int $width, int $gap) {
    $actions = Navigation::quickActions(userWithRole($role));

    expect(array_column($actions['items'], 'label'))->toBe($labels)
        ->and($actions['width'])->toBe($width)
        ->and($actions['gap'])->toBe($gap);
})->with([
    'admin' => [Role::ADMIN, ['Add User', 'Export List', 'Interventions', 'Upload Excel', 'Inventory'], 176, 14],
    'agritech' => [Role::AGRITECH, ['File Disaster Report', 'Interventions'], 226, 17],
    'encoder' => [Role::ENCODER, ['Upload Excel', 'Add Beneficiary', 'Disaster Reports'], 200, 22],
]);

it('shows each role\'s stat labels with Figma colors', function () {
    $stats = Navigation::stats(userWithRole(Role::AGRITECH));

    expect(array_column($stats, 'label'))->toBe(['Pending Validation', 'Active Interventions', 'Reports Filed This Month'])
        ->and(array_column($stats, 'color'))->toBe(['#da37ff', '#8037ff', '#4671ff']);
});

it('counts active users with a role', function () {
    $admin = userWithRole(Role::ADMIN);
    userWithRole(Role::ENCODER);
    userWithRole(Role::ENCODER, ['status' => User::STATUS_INACTIVE]);
    userWithRole(null);

    $activeUsers = collect(Navigation::stats($admin))->firstWhere('label', 'Active Users');

    expect($activeUsers['value'])->toBe(2);
});

it('renders the dashboard shell for every role', function (string $role, string $greeting, string $firstStat) {
    $user = userWithRole($role);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Agapay')
        ->assertSee(now()->format('D, F j'))
        ->assertSee('HOME DASHBOARD')
        ->assertSee("Hello, {$greeting}")
        ->assertSee('Select an action to get started:')
        ->assertSee('Search beneficiary by name, RSBSA No., or barangay...', false)
        ->assertSee($user->username)
        ->assertSee($firstStat)
        ->assertSee('RETURN TO LOGIN');
})->with([
    [Role::ADMIN, 'Admin', 'Total Beneficiaries'],
    [Role::AGRITECH, 'Agritech', 'Pending Validation'],
    [Role::ENCODER, 'Encoder', 'Encoded This Month'],
]);

it('can pin the header date for visual comparison without moving the real clock', function () {
    config(['agapay.frozen_now' => '2026-07-22 09:00:00']);

    $response = $this->actingAs(userWithRole(Role::ADMIN))->get('/dashboard')->assertSee('Wed, July 22');

    // The session cookie must still be valid (a frozen global clock expired it, breaking login).
    $session = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === config('session.cookie'));
    expect($session->getExpiresTime())->toBeGreaterThan(time());
});

it('lists remembered accounts in the account menu', function () {
    $admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $agritech = userWithRole(Role::AGRITECH, ['username' => 'Agritech_02']);
    $inactive = userWithRole(Role::ENCODER, ['username' => 'Encoder_05', 'status' => User::STATUS_INACTIVE]);

    $this->actingAs($admin)
        ->withCookie(KnownAccounts::COOKIE, json_encode([$admin->id, $agritech->id, $inactive->id, 99999]))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Agritech_02')
        ->assertDontSee('Encoder_05');
});

it('ignores a garbage remembered-accounts cookie', function () {
    $this->actingAs(userWithRole(Role::ADMIN))
        ->withCookie(KnownAccounts::COOKIE, 'not-json{')
        ->get('/dashboard')
        ->assertOk();
});
