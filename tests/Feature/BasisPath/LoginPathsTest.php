<?php

use App\Models\Role;
use App\Models\User;

// Basis path testing of LoginRequest::authenticate (manuscript section 3.4.2). Decisions:
//   P1 too many attempts?  P2 unknown username or wrong password?  P3 account cannot sign in?  P4 account active?
// V(G) = 4 + 1 = 5 independent paths.

beforeEach(function () {
    seedRoles();
    $this->user = userWithRole(Role::ENCODER, ['username' => 'Encoder_07', 'password' => 'Correct1!']);
});

it('path 1: P1 true - locked out after five failed attempts', function () {
    $this->freezeTime();   // the message counts down the lockout's seconds
    foreach (range(1, 5) as $_) {
        $this->post('/login', ['username' => 'Encoder_07', 'password' => 'wrong']);
    }

    $this->post('/login', ['username' => 'Encoder_07', 'password' => 'Correct1!'])
        ->assertSessionHasErrors(['username' => 'Too many login attempts. Please try again in 60 seconds.']);
    $this->assertGuest();
});

it('path 2: P1 false, P2 true - unknown username or wrong password', function () {
    $this->post('/login', ['username' => 'Nobody', 'password' => 'Correct1!'])
        ->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);
    $this->post('/login', ['username' => 'Encoder_07', 'password' => 'Wrong123'])
        ->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);
    $this->assertGuest();
});

it('path 3: P2 false, P3 true, P4 false - inactive account', function () {
    $this->user->update(['status' => User::STATUS_INACTIVE]);

    $this->post('/login', ['username' => 'Encoder_07', 'password' => 'Correct1!'])
        ->assertSessionHasErrors(['username' => 'This account is inactive. Contact the administrator.']);
    $this->assertGuest();
});

it('path 4: P2 false, P3 true, P4 true - active account without a role', function () {
    $this->user->update(['role_id' => null]);

    $this->post('/login', ['username' => 'Encoder_07', 'password' => 'Correct1!'])
        ->assertSessionHasErrors(['username' => 'This account has no role assigned. Contact the administrator.']);
    $this->assertGuest();
});

it('path 5: P1, P2 and P3 false - signed in', function () {
    $this->post('/login', ['username' => 'encoder_07', 'password' => 'Correct1!'])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);
});
