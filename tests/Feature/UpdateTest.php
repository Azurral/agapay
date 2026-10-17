<?php

use App\Models\Role;
use App\Support\UpdateChecker;
use App\Support\UpdateLauncher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRoles();
    config(['agapay.version' => '0.14.1', 'agapay.updates.enabled' => true, 'agapay.updates.repository' => 'Azurral/agapay']);
    Cache::flush();
    $this->admin = userWithRole(Role::ADMIN);
});

function fakeRelease(string $tag = 'v0.15.0', array $assets = ['AGAPAY-app-v0.15.0.zip'], int $status = 200): void
{
    Http::fake(['api.github.com/repos/Azurral/agapay/releases/latest' => Http::response([
        'tag_name' => $tag,
        'body' => "- Programs page\n- Faster import",
        'assets' => array_map(fn ($name) => ['name' => $name, 'browser_download_url' => "https://github.com/Azurral/agapay/releases/download/{$tag}/{$name}"], $assets),
    ], $status)]);
}

it('shows the administrator an Update available card when GitHub has a newer release', function () {
    fakeRelease();

    $this->actingAs($this->admin)->get('/dashboard')->assertOk()
        ->assertSeeInOrder(['UPDATE AVAILABLE', 'v0.15.0 is ready', 'DARK MODE'])
        ->assertSee('Programs page');
});

it('shows no card to other roles, for the same version, without a package, when offline or when updates are off', function (Closure $setup, ?string $role) {
    $setup();

    $this->actingAs($role ? userWithRole($role) : $this->admin)->get('/dashboard')->assertOk()->assertDontSee('UPDATE AVAILABLE');
})->with([
    'encoder' => [fn () => fakeRelease(), Role::ENCODER],
    'same version' => [fn () => fakeRelease('v0.14.1'), null],
    'older version' => [fn () => fakeRelease('v0.13.0'), null],
    'no app package' => [fn () => fakeRelease('v0.15.0', ['notes.txt']), null],
    'github down' => [fn () => fakeRelease(status: 500), null],
    'offline' => [fn () => Http::fake(fn () => throw new ConnectionException('offline')), null],
    'updates off (online copy)' => [function () {
        fakeRelease();
        config(['agapay.updates.enabled' => false]);
    }, null],
]);

it('checks GitHub at most every few hours', function () {
    fakeRelease();
    app(UpdateChecker::class)->latest();
    app(UpdateChecker::class)->latest();

    Http::assertSentCount(1);
});

it('starts the updater with the release package when the administrator confirms', function () {
    fakeRelease();
    $launcher = Mockery::mock(UpdateLauncher::class);
    $launcher->shouldReceive('launch')->once()->with('0.15.0', 'https://github.com/Azurral/agapay/releases/download/v0.15.0/AGAPAY-app-v0.15.0.zip');
    app()->instance(UpdateLauncher::class, $launcher);

    $this->actingAs($this->admin)->post(route('system.update'))->assertOk()
        ->assertSee('Updating AGAPAY to v0.15.0');
});

it('refuses to update when there is nothing newer or the user is not the administrator', function () {
    fakeRelease('v0.14.1');
    $this->actingAs($this->admin)->post(route('system.update'))->assertRedirect()->assertSessionHas('status', 'AGAPAY is already up to date.');

    fakeRelease();
    $this->actingAs(userWithRole(Role::ENCODER))->post(route('system.update'))->assertForbidden();
});

it('sends security headers on every page', function () {
    $this->get('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');
});
