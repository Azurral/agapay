<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $this->roleId = fn (string $slug) => Role::where('slug', $slug)->value('id');
});

function newUserInput(array $overrides = []): array
{
    return [
        'last_name' => 'Santos', 'first_name' => 'Catherine', 'middle_initial' => 'm', 'suffix' => '',
        'username' => 'catherine', 'password' => 'office-pass-1', 'password_confirmation' => 'office-pass-1',
        'role_id' => Role::where('slug', Role::ADMIN)->value('id'), 'status' => User::STATUS_ACTIVE, ...$overrides,
    ];
}

it('saves a user from separate name fields and shows the full name', function () {
    $this->actingAs($this->admin)->post('/users', newUserInput(['suffix' => 'Jr.']))->assertSessionHasNoErrors();

    $user = User::where('username', 'catherine')->sole();
    expect($user)->last_name->toBe('Santos')->first_name->toBe('Catherine')->middle_initial->toBe('M')->suffix->toBe('Jr.')
        ->and($user->name)->toBe('Catherine M. Santos Jr.')
        ->and($user->initials())->toBe('CS');
});

it('builds the full name without the optional parts', function () {
    $this->actingAs($this->admin)->post('/users', newUserInput(['middle_initial' => '', 'username' => 'marjie', 'first_name' => 'Marjie', 'last_name' => 'Dela Cruz']));

    expect(User::where('username', 'marjie')->sole()->name)->toBe('Marjie Dela Cruz');
});

it('requires the first and last name and keeps the middle initial and suffix short', function (array $overrides, string $field) {
    $this->actingAs($this->admin)->post('/users', newUserInput($overrides))->assertSessionHasErrorsIn('createUser', $field);
})->with([
    'no last name' => [['last_name' => ''], 'last_name'],
    'no first name' => [['first_name' => ''], 'first_name'],
    'middle name instead of initial' => [['middle_initial' => 'Manalo'], 'middle_initial'],
    'unknown suffix' => [['suffix' => 'Esq'], 'suffix'],
]);

it('edits the name parts of a user', function () {
    $user = userWithRole(Role::ENCODER, ['username' => 'Encoder_03']);

    $this->actingAs($this->admin)->put(route('users.update', $user), newUserInput([
        'username' => 'Encoder_03', 'first_name' => 'Sheryll Kate', 'last_name' => 'Bayang', 'middle_initial' => 'T.',
        'password' => '', 'password_confirmation' => '', 'role_id' => ($this->roleId)(Role::ENCODER),
    ]))->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Sheryll Kate T. Bayang');
});

it('shows the name parts and a live password guide on the user forms', function () {
    $this->actingAs($this->admin)->get('/users?add=1')->assertOk()
        ->assertSeeInOrder(['Last Name', 'First Name', 'M.I.', 'Suffix', 'Username'])
        ->assertSee('At least 8 characters')->assertSee('The two passwords match');
});

it('shows the password guide on Change Password too', function () {
    $this->actingAs($this->admin)->get(route('password.edit'))->assertOk()
        ->assertSee('At least 8 characters')->assertSee('The two passwords match');
});
