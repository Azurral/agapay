<?php

// AGAPAY runs on the office laptop with no proxy in front of it, so forwarded headers must never change its links.

it('ignores forwarded headers', function () {
    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
        ->get('/login')
        ->assertDontSee('https://evil.example', false)
        ->assertSee('action="'.url('/login').'"', false);
});
