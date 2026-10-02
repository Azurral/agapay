<?php

use App\Models\Role;

it('shows the Agapay logo on the login screen and in the app header', function () {
    seedRoles();

    $this->get('/login')->assertOk()
        ->assertSee('src="'.asset('images/logo.svg').'"', false)
        ->assertSee('alt="Agapay logo"', false)
        ->assertDontSee('logo-box', false);

    $this->actingAs(userWithRole(Role::ADMIN))->get('/dashboard')->assertOk()
        ->assertSee('src="'.asset('images/logo.svg').'"', false)
        ->assertDontSee('logo-box', false);
});

it('uses the logo as the browser tab icon on every layout', function () {
    seedRoles();
    $icon = '<link rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml">';

    $this->get('/login')->assertSee($icon, false);
    $this->actingAs(userWithRole(Role::ADMIN))->get('/dashboard')->assertSee($icon, false);
});

it('ships the logo files', function (string $path) {
    expect(public_path($path))->toBeFile()
        ->and(filesize(public_path($path)))->toBeGreaterThan(100);
})->with(['images/logo.svg', 'favicon.svg', 'favicon.ico']);
