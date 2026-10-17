<?php

use App\Models\AuditLog;
use App\Models\Intervention;
use App\Models\Role;
use Database\Seeders\InterventionSeeder;

beforeEach(function () {
    seedRoles();
    $this->seed(InterventionSeeder::class);
    $this->admin = userWithRole(Role::ADMIN);
});

function programInput(array $overrides = []): array
{
    return ['source' => 'lgu', 'name' => 'Heirloom Rice Seeds', 'unit' => 'kg', 'one_per_household' => '0', 'allow_repeat' => '0', 'is_active' => '1', ...$overrides];
}

it('lists the DA and LGU programs for the administrator, linked from Interventions', function () {
    $this->actingAs($this->admin)->get('/interventions')->assertSee(route('programs.index'), false)->assertSee('Programs');

    $this->get('/programs')->assertOk()->assertSee('<p>PROGRAMS</p>', false)
        ->assertSeeInOrder(['DA Programs', 'Certified Rice Seeds', 'LGU Programs', 'Emergency Seedlings']);
});

it('adds a program that is then offered when encoding records', function () {
    $this->actingAs($this->admin)->post('/programs', programInput())
        ->assertRedirect(route('programs.index'))->assertSessionHas('status', 'LGU - Heirloom Rice Seeds added.');

    $program = Intervention::where('name', 'Heirloom Rice Seeds')->sole();
    expect($program)->source->toBe('lgu')->unit->toBe('kg')->one_per_household->toBeFalse()->is_active->toBeTrue()
        ->and(AuditLog::where('action', 'Added Program')->exists())->toBeTrue();

    $encoder = userWithRole(Role::ENCODER);
    $this->actingAs($encoder)->get('/intervention-records/create')->assertSee('Heirloom Rice Seeds');
});

it('edits a program and can deactivate it so it is no longer offered', function () {
    $seedlings = Intervention::where(['source' => 'lgu', 'name' => 'Emergency Seedlings'])->sole();

    $this->actingAs($this->admin)->put(route('programs.update', $seedlings), programInput(['name' => 'Emergency Vegetable Seedlings', 'unit' => 'pack', 'allow_repeat' => '1', 'is_active' => '0']))
        ->assertRedirect(route('programs.index'));

    expect($seedlings->fresh())->name->toBe('Emergency Vegetable Seedlings')->allow_repeat->toBeTrue()->is_active->toBeFalse();
    $this->actingAs(userWithRole(Role::ENCODER))->get('/intervention-records/create')->assertDontSee('Emergency Vegetable Seedlings');
});

it('refuses a duplicate name in the same source, ignoring letter case, but allows it in the other source', function () {
    $this->actingAs($this->admin)->post('/programs', programInput(['source' => 'da', 'name' => '  certified RICE seeds ']))
        ->assertSessionHasErrors(['name' => 'That program already exists under DA.']);

    $this->post('/programs', programInput(['source' => 'lgu', 'name' => 'Certified Rice Seeds']))->assertSessionHasNoErrors();
});

it('validates the program fields', function (array $overrides, string $field) {
    $this->actingAs($this->admin)->post('/programs', programInput($overrides))->assertSessionHasErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'unknown source' => [['source' => 'ngo'], 'source'],
    'long unit' => [['unit' => str_repeat('x', 21)], 'unit'],
]);

it('is for administrators only', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/programs')->assertForbidden();
    $this->post('/programs', programInput())->assertForbidden();
})->with([Role::AGRITECH, Role::ENCODER, Role::AGRITECH_ENCODER]);
