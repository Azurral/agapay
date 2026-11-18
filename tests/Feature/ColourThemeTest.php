<?php

use App\Models\Role;

// Colour themes (panel: a government colourway): Navy & Gold (LGU Bontoc) by default, Green & Cream (Department of
// Agriculture) and Violet & Blue (the original), each in light and dark mode, saved on the account.

beforeEach(function () {
    seedRoles();
    $this->user = userWithRole(Role::ENCODER);
});

it('starts in Navy & Gold, light', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()
        ->assertSee('data-palette="navy"', false)
        ->assertDontSee('<html lang="en" class="dark', false);
});

it('saves the colour theme and dark mode on the account', function () {
    $this->actingAs($this->user)->postJson('/account/appearance', ['palette' => 'green', 'dark' => true])->assertNoContent();

    expect($this->user->fresh())->color_theme->toBe('green')->dark_mode->toBeTrue();
    $this->actingAs($this->user->fresh())->get('/dashboard')->assertOk()
        ->assertSee('data-palette="green"', false)
        ->assertSee('data-dark="1"', false);
});

it('can change one of the two alone', function () {
    $this->actingAs($this->user)->postJson('/account/appearance', ['palette' => 'violet'])->assertNoContent();
    $this->actingAs($this->user)->postJson('/account/appearance', ['dark' => false])->assertNoContent();

    expect($this->user->fresh())->color_theme->toBe('violet')->dark_mode->toBeFalse();
});

it('refuses a theme that does not exist', function () {
    $this->actingAs($this->user)->postJson('/account/appearance', ['palette' => 'pink'])->assertUnprocessable();

    expect($this->user->fresh()->color_theme)->toBeNull();
});

it('is only for signed-in users', function () {
    $this->postJson('/account/appearance', ['palette' => 'green'])->assertUnauthorized();
});

it('offers the three colourways in the side panel', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()
        ->assertSeeInOrder(['DARK MODE', 'Navy &amp; Gold', 'Green &amp; Cream', 'Violet &amp; Blue', 'RETURN TO LOGIN'], false)
        ->assertSee('role="radiogroup"', false);
});

it('leaves no fixed violet in the pages, so every theme reaches every page', function () {
    $hits = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($f) => str_contains($f->getFilename(), 'pdf') || str_contains($f->getPathname(), DIRECTORY_SEPARATOR.'pdf'))
        ->filter(fn ($f) => preg_match('/#(5a5de3|7e80ff|efeaff|4b32c3|4b4fc4|2b2d6e|00fff2)\b|rgba\(90,93,227/i', $f->getContents()))
        ->map(fn ($f) => $f->getRelativePathname())->values()->all();

    expect($hits)->toBe([]);
});

it('keeps pop-up windows inside the grid area, below the header and beside the side panel', function () {
    $this->actingAs(userWithRole(Role::ADMIN))->get('/damage-reports')->assertOk()->assertSee('modal-area', false);
    expect(file_get_contents(resource_path('css/app.css')))->toContain('.modal-area');
});

// v0.18.1 (user): the two new themes are flat (no gradients), the header is the theme's main colour in light and dark
// mode, and effects flip with the mode (a dark shadow in light mode, a light glow in dark mode). Violet never changes.

function flatLayer(): string
{
    $css = file_get_contents(resource_path('css/app.css'));

    return Str::betweenFirst($css, '/* ==== Flat themes', '/* ==== End of flat themes');
}

it('scopes every rule of the flat themes away from Violet & Blue', function () {
    $css = preg_replace('#/\*.*?\*/#s', '', '/*'.flatLayer());   // comments out (the section starts inside one)
    $rules = preg_match_all('/([^{}]+)\{[^{}]*\}/', $css, $m) ? $m[1] : [];

    expect($rules)->not->toBeEmpty();
    foreach ($rules as $selectors) {
        foreach (array_filter(array_map('trim', explode(',', $selectors))) as $selector) {
            expect($selector)->toMatch('/^html(\.dark)?(:not\(\[data-palette="violet"\]\)|\[data-palette="green"\])/', "unscoped: {$selector}");
        }
    }
});

it('keeps Violet & Blue exactly as it was: its colours and its gradient images', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain("html[data-palette=\"violet\"] .bg-brand-button { background-image: url('/images/figma/gradients/button.svg'); }")
        ->toContain('--color-brand: #5a5de3;')
        ->and(file_exists(public_path('images/figma/gradients/button.svg')))->toBeTrue();
});

it('draws the new themes flat, with no gradients', function () {
    $flat = flatLayer();

    expect($flat)->toContain('.bg-brand-button')->toContain('.border-gradient')->toContain('.text-gradient-heading')
        ->toContain('background-image: none')
        ->not->toContain('linear-gradient(90deg, var(--color-accent)')
        ->and(glob(public_path('images/figma/gradients/navy/*.svg')))->toBe([])
        ->and(glob(public_path('images/figma/gradients/green/*.svg')))->toBe([]);
});

it('paints the header in the main colour in light and dark mode', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('site-header', false);

    expect(flatLayer())->toMatch('/html:not\(\[data-palette="violet"\]\) \.site-header[^{]*\{[^}]*background-color: var\(--color-brand\)/')
        ->not->toMatch('/html\.dark[^{]*\.site-header[^{]*\{[^}]*background-color: var\(--dark/');
});

it('lifts buttons with a dark shadow in light mode and a light glow in dark mode', function () {
    $flat = flatLayer();

    expect($flat)->toMatch('/html:not\(\[data-palette="violet"\]\) \{[^}]*--lift: 0 4px 12px rgb\(0 0 0/')
        ->toMatch('/html\.dark:not\(\[data-palette="violet"\]\) \{[^}]*--lift:[^;]*rgb\(255 255 255/');
});

// v0.18.2 (user): charts in each theme's own colourway. Navy: navy / light blue / gold. Green: dark green / light
// green / gold, all solid colours (no stripes or patterns).

function tokenBlock(string $selector): string
{
    $css = file_get_contents(resource_path('css/app.css'));
    preg_match('/^'.preg_quote($selector, '/').' \{([^}]*)\}/m', $css, $m);

    return $m[1] ?? '';
}

it('draws the charts in each new theme\'s own colours', function () {
    foreach ([':root', 'html[data-palette="green"]', 'html.dark', 'html.dark[data-palette="green"]'] as $block) {
        expect(tokenBlock($block))->not->toBeEmpty()
            ->not->toMatch('/--chart-(waiting|notclaimable): #(2aa0b5|1e9e8a|c0507f|cf5f8f|2a7fb8|4a86d6|d0733f)/i', "{$block} still has an off-palette chart colour");
    }
    expect(tokenBlock(':root'))->toContain('--chart-waiting: #6fa8e8')->toContain('--chart-notclaimable: #d99a00')
        ->and(tokenBlock('html[data-palette="green"]'))->toContain('--chart-waiting: #6dbb5a')->toContain('--chart-notclaimable: #d99a00');
});

it('draws every chart colour solid, with no stripes or patterns', function () {
    expect(file_get_contents(resource_path('css/app.css')))->not->toContain('repeating-linear-gradient');
});

it('keeps Violet & Blue\'s chart colours in light and dark mode', function () {
    expect(tokenBlock('html[data-palette="violet"]'))->toContain('--chart-waiting: #2aa7c4')->toContain('--chart-notclaimable: #d0733f')
        ->and(tokenBlock('html.dark[data-palette="violet"]'))->toContain('--chart-waiting: #1e93ad')->toContain('--chart-notclaimable: #d0733f');
});

it('gives Green & Cream the same gold as Navy & Gold', function () {
    expect(tokenBlock('html[data-palette="green"]'))->toContain('--color-accent: #f5c518')->toContain('--chart-notclaimable: #d99a00')
        ->and(tokenBlock('html.dark[data-palette="green"]'))->toContain('--dark-brand: #f5c84c')->toContain('--chart-notclaimable: #b8891d');
});
