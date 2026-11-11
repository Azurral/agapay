<?php

use App\Models\Role;

beforeEach(fn () => seedRoles());

it('marks every page of an evaluation copy as sample data', function () {
    config(['agapay.evaluation' => true]);

    $this->get('/login')->assertOk()->assertSee('Sample records only')->assertSee('Admin_01');
    $this->actingAs(userWithRole(Role::ADMIN))
        ->get('/dashboard')->assertOk()->assertSee('Sample records only');
});

it('shows no evaluation mark or sample accounts on a real copy', function () {
    $this->get('/login')->assertOk()->assertDontSee('Sample records only')->assertDontSee('Admin_01');
});
