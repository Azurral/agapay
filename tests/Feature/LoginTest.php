<?php

use App\Models\Role;
use App\Models\User;
use App\Support\KnownAccounts;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01', 'password' => 'secret-pass']);
});

it('shows the Figma login screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSeeInOrder(['Agapay', 'Username', 'Password', 'LOGIN']);
});

it('prefills the username from the account switcher', function () {
    $this->get('/login?username=Agritech_02')->assertSee('value="Agritech_02"', false);
});

it('logs in by username, ignoring letter case', function () {
    $this->post('/login', ['username' => 'ADMIN_01', 'password' => 'secret-pass'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->admin);
    expect($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password', function () {
    $this->from('/login')
        ->post('/login', ['username' => 'Admin_01', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);

    $this->assertGuest();
});

it('blocks inactive accounts', function () {
    userWithRole(Role::ENCODER, ['username' => 'Encoder_09', 'password' => 'secret-pass', 'status' => User::STATUS_INACTIVE]);

    $this->post('/login', ['username' => 'Encoder_09', 'password' => 'secret-pass'])
        ->assertSessionHasErrors(['username' => 'This account is inactive. Contact the administrator.']);

    $this->assertGuest();
});

it('blocks accounts without a role', function () {
    userWithRole(null, ['username' => 'Encoder_04', 'password' => 'secret-pass']);

    $this->post('/login', ['username' => 'Encoder_04', 'password' => 'secret-pass'])
        ->assertSessionHasErrors(['username' => 'This account has no role assigned. Contact the administrator.']);

    $this->assertGuest();
});

it('throttles after five failed attempts', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['username' => 'Admin_01', 'password' => 'wrong']);
    }

    $this->post('/login', ['username' => 'Admin_01', 'password' => 'secret-pass'])
        ->assertSessionHasErrors('username');

    expect(session('errors')->first('username'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

it('remembers the account on this device', function () {
    $this->post('/login', ['username' => 'Admin_01', 'password' => 'secret-pass'])
        ->assertCookie(KnownAccounts::COOKIE, json_encode([$this->admin->id]));
});

it('logs out', function () {
    $this->actingAs($this->admin)->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('switches account by returning to login with the username filled in', function () {
    $this->actingAs($this->admin)
        ->post('/switch-account', ['username' => 'Agritech_02'])
        ->assertRedirect(route('login', ['username' => 'Agritech_02']));

    $this->assertGuest();
});

it('kicks out a user deactivated mid-session', function () {
    $this->actingAs($this->admin);
    $this->admin->update(['status' => User::STATUS_INACTIVE]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('sends guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/')->assertRedirect(route('dashboard'));
});

it('sends signed-in users away from the login page', function () {
    $this->actingAs($this->admin)->get('/login')->assertRedirect(route('dashboard'));
});
