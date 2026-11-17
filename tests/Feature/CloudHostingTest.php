<?php

use App\Models\DamagePhoto;
use App\Models\GeneratedReport;

// The cloud copy runs behind the host's HTTPS proxy; the office copy has no proxy in front of it.

/** Sets (or, with null, clears) TRUSTED_PROXIES and restarts the app so bootstrap/app.php reads it. */
function bootWithTrustedProxies(?string $proxies): void
{
    if ($proxies === null) {
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    } else {
        putenv("TRUSTED_PROXIES={$proxies}");
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $proxies;
    }

    test()->refreshApplication();
}

afterEach(function () {
    bootWithTrustedProxies(null);
});

it('builds https links behind a trusted hosting proxy', function () {
    bootWithTrustedProxies('*');

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'agapay.example.org'])
        ->get('/login')
        ->assertSee('action="https://agapay.example.org/login"', false);
});

it('tells browsers to use https only (HSTS) on secure requests', function () {
    bootWithTrustedProxies('*');

    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('sends no HSTS header over plain http (the office copy on localhost)', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
});

it('tells farmers where the cloud copy keeps their data and how it is protected', function () {
    config(['agapay.edition' => 'cloud']);

    $this->get('/privacy')->assertOk()
        ->assertSee('secure cloud server')
        ->assertSee('encrypted')
        ->assertSee('HTTPS')
        ->assertDontSee('On the OMAG office computer');
});

it('keeps the office wording on the office copy', function () {
    $this->get('/privacy')->assertOk()->assertSee('On the OMAG office computer');
});

it('keeps photos and reports on the disk set in the config', function () {
    expect(DamagePhoto::disk())->toBe('local')
        ->and(GeneratedReport::disk())->toBe('local');

    config(['agapay.files_disk' => 's3']);

    expect(DamagePhoto::disk())->toBe('s3')
        ->and(GeneratedReport::disk())->toBe('s3');
});
