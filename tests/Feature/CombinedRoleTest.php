<?php

use App\Models\Beneficiary;
use App\Models\Role;
use App\Support\Navigation;
use App\Support\PermissionCatalog;
use Database\Seeders\BarangaySeeder;

beforeEach(fn () => seedRoles());

it('has a combined Agricultural Technologist and Data Encoder role with both sets of permissions', function () {
    $role = Role::where('slug', Role::AGRITECH_ENCODER)->sole();
    $expected = collect(PermissionCatalog::defaults()[Role::AGRITECH])->merge(PermissionCatalog::defaults()[Role::ENCODER])->unique()->sort()->values()->all();

    expect($role->name)->toBe('Agricultural Technologist & Data Encoder')
        ->and($role->permissions()->pluck('slug')->sort()->values()->all())->toBe($expected);
});

it('merges both menus and quick actions for the combined role', function () {
    $user = userWithRole(Role::AGRITECH_ENCODER);

    expect(array_column(Navigation::items($user), 'label'))->toBe([
        'Home', 'Beneficiary Profiles', 'Intervention Records', 'DA Intervention', 'LGU Intervention', 'Crisis Reports', 'Inventory', 'Download Reports',
    ])->and(array_column(Navigation::quickActions($user)['items'], 'label'))->toBe([
        'Upload Excel', 'Add Beneficiary', 'File Crisis Report', 'New Request',
    ]);
});

it('lets the combined role do both jobs', function (string $url) {
    $this->actingAs(userWithRole(Role::AGRITECH_ENCODER))->get($url)->assertOk();
})->with(['/beneficiaries', '/beneficiaries/create', '/intervention-records', '/inventory', '/import', '/interventions/da', '/damage-reports/create', '/assistance-requests/create']);

it('keeps administrator pages closed to the combined role', function (string $url) {
    $this->actingAs(userWithRole(Role::AGRITECH_ENCODER))->get($url)->assertForbidden();
})->with(['/users', '/audit-trail', '/distribution-cycles', '/export']);

it('gives the combined role its own avatar colour and short name', function () {
    $user = userWithRole(Role::AGRITECH_ENCODER, ['name' => 'Marjie']);

    expect($user->roleShortName())->toBe('AgriTech & Encoder')
        ->and($user->avatarColor())->toBe('#2f8fff');
});

it('opens beneficiary profiles in edit mode for the combined role, like an encoder', function () {
    $this->seed([BarangaySeeder::class]);
    $b = Beneficiary::factory()->create();

    $this->actingAs(userWithRole(Role::AGRITECH_ENCODER))->get(route('beneficiaries.show', $b))
        ->assertOk()->assertSee('EDIT MODE');
});
