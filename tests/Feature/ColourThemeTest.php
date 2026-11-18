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
