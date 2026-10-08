<?php

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

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'agapay.up.railway.app'])
        ->get('/login')
        ->assertSee('action="https://agapay.up.railway.app/login"', false);
});

it('ignores forwarded headers when no proxy is trusted', function () {
    bootWithTrustedProxies(null);

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
        ->get('/login')
        ->assertDontSee('https://evil.example', false)
        ->assertSee('action="'.url('/login').'"', false);
});
