<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\DashboardWidgets;
use Database\Seeders\DatabaseSeeder;

// Each user chooses what their home page shows (checkboxes), starting from their role's defaults.

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

it('starts each role with its own default widgets', function () {
    expect(DashboardWidgets::chosen($this->admin))->toContain('walkins', 'tile_low_stock', 'chart_stock')
        ->and(DashboardWidgets::chosen($this->agritech))->toContain('damage', 'to_verify')->not->toContain('walkins')
        ->and(DashboardWidgets::chosen($this->encoder))->toContain('encoding_queue', 'chart_stock')->not->toContain('walkins');
});

it('offers only the widgets the user\'s role may see', function () {
    expect(array_keys(DashboardWidgets::allowed($this->agritech)))->not->toContain('walkins', 'chart_stock', 'tile_low_stock')
        ->and(array_keys(DashboardWidgets::allowed($this->admin)))->toContain('walkins', 'chart_stock');
});

it('saves the user\'s choice and order, and Home follows it', function () {
    $this->actingAs($this->admin)->put(route('dashboard.customize'), ['widgets' => ['chart_barangays', 'tile_farmers']])
        ->assertRedirect(route('dashboard'))->assertSessionHas('status', 'Home page saved.');

    expect($this->admin->fresh()->dashboard_widgets)->toBe(['chart_barangays', 'tile_farmers'])
        ->and(AuditLog::where('action', 'Customized Home Page')->exists())->toBeTrue();

    $this->get('/dashboard')->assertOk()
        ->assertSee('Farmers per barangay')->assertSee('Registered farmers')
        ->assertDontSee('low-stock level')               // the stock chart's legend
        ->assertDontSee('Open Walk-in Approvals');       // the walk-ins panel's link
});

it('ignores widgets the user may not see, even in a forged request', function () {
    $this->actingAs($this->agritech)->put(route('dashboard.customize'), ['widgets' => ['walkins', 'tile_farmers', 'nonsense']]);

    expect($this->agritech->fresh()->dashboard_widgets)->toBe(['tile_farmers']);
});

it('resets to the role\'s default', function () {
    $this->actingAs($this->encoder)->put(route('dashboard.customize'), ['widgets' => ['tile_farmers']]);
    $this->put(route('dashboard.customize'), ['reset' => '1'])->assertSessionHas('status', 'Home page reset to the default for your role.');

    expect($this->encoder->fresh()->dashboard_widgets)->toBeNull()
        ->and(DashboardWidgets::chosen($this->encoder->fresh()))->toBe(DashboardWidgets::defaults($this->encoder));
});

it('shows a Customize button with a checkbox for each allowed widget', function () {
    $this->actingAs($this->agritech)->get('/dashboard')->assertOk()
        ->assertSee('Customize')
        ->assertSee('name="widgets[]"', false)
        ->assertSee('Reset to the default for my role')
        ->assertDontSee('value="walkins"', false);
});

it('keeps a hand-picked empty home page possible', function () {
    $this->actingAs($this->admin)->put(route('dashboard.customize'), ['widgets' => []])->assertSessionHasNoErrors();

    expect($this->admin->fresh()->dashboard_widgets)->toBe([]);
    $this->get('/dashboard')->assertOk()->assertSee('Your home page is empty');
});
