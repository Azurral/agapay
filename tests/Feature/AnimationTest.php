<?php

use App\Models\AssistanceRequest;
use App\Models\InterventionRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
});

it('draws the logo inline so its bars can grow, on the landing, login and app header', function (string $url, bool $signedIn) {
    $response = ($signedIn ? $this->actingAs($this->admin) : $this)->get($url)->assertOk();

    expect(substr_count($response->getContent(), 'class="logo-bar"'))->toBeGreaterThanOrEqual(3)
        ->and($response->getContent())->toContain('class="agapay-logo')->toContain('class="logo-leaf"');
})->with([
    'landing' => ['/', false],
    'login' => ['/login', false],
    'dashboard' => ['/dashboard', true],
]);

it('keeps the leaf riding on top of the tallest bar so they never overlap', function () {
    $html = $this->get('/login')->getContent();

    expect($html)->toMatch('/<g class="logo-ride">\s*<path class="logo-leaf"/');
});

it('brings the landing page in step by step, the log in button last', function () {
    $html = $this->get('/')->getContent();
    preg_match_all('/class="[^"]*enter-(rise|settle)[^"]*"[^>]*style="--d: (\d+)ms"/', $html, $m);

    $delays = array_map('intval', $m[2]);
    $settle = $delays[array_search('settle', $m[1])];

    expect(count($m[0]))->toBeGreaterThanOrEqual(7)
        ->and(substr_count($html, 'enter-settle'))->toBe(1)
        ->and($settle)->toBe(max($delays));

    preg_match_all('/<article class="enter-rise[^"]*" style="--d: (\d+)ms"/', $html, $cards);
    expect($cards[1])->toHaveCount(4)
        ->and(array_map('intval', $cards[1]))->toBe(collect($cards[1])->map(fn ($d) => (int) $d)->sort()->values()->all());
});

it('counts the summary numbers up', function () {
    $requests = $this->actingAs($this->admin)->get('/interventions/lgu?tab=requests')->getContent();
    $damage = $this->get('/damage-reports')->getContent();

    expect(substr_count($requests, 'data-count-up'))->toBe(5)
        ->and(substr_count($damage, 'data-count-up'))->toBeGreaterThanOrEqual(3);
});

it('shows a spinner on downloads', function (string $url, string $route) {
    $html = $this->actingAs($this->admin)->get($url)->getContent();

    expect($html)->toMatch('/<a href="'.preg_quote(route($route), '/').'[^"]*"[^>]*data-busy/');
})->with([
    'requests export' => ['/interventions/lgu?tab=requests', 'assistance-requests.export'],
    'beneficiary export' => ['/export', 'export.download'],
    'damage export' => ['/damage-reports', 'damage.export'],
]);

it('morphs the dark mode sun into a moon instead of swapping them', function () {
    $card = Str::betweenFirst($this->actingAs($this->admin)->get('/dashboard')->getContent(), 'aria-label="Dark mode"', '</button>');

    expect($card)->toContain('theme-sun')->toContain('theme-moon')->not->toContain('x-show');
});

it('slides messages in and lets only success messages fade away', function () {
    $this->actingAs($this->admin)->post(route('assistance-requests.deny', AssistanceRequest::where('status', AssistanceRequest::PENDING)->first()), ['decision_note' => '']);
    $error = $this->get('/interventions/lgu?tab=requests')->getContent();
    expect($error)->toMatch('/class="flash [^"]*"[^>]*role="alert"/')->not->toContain('data-autohide');

    $this->post(route('assistance-requests.deny', AssistanceRequest::where('status', AssistanceRequest::PENDING)->first()), ['decision_note' => 'No stock']);
    expect($this->get('/interventions/lgu?tab=requests')->getContent())->toMatch('/class="flash [^"]*"[^>]*role="status" data-autohide/');
});

it('staggers the side panel items in', function () {
    $html = $this->actingAs($this->admin)->get('/dashboard')->getContent();

    expect($html)->toContain('nav-enter')->toContain('style="--i: 0"')->toContain('style="--i: 1"');
});

it('pulses a dot on pending requests only', function () {
    $html = $this->actingAs($this->admin)->get('/interventions/lgu?tab=requests&status=pending')->getContent();
    $denied = $this->get('/interventions/lgu?tab=requests&status=denied')->getContent();

    expect(substr_count($html, 'class="chip-dot"'))->toBe(min(AssistanceRequest::where('status', AssistanceRequest::PENDING)->count(), 15))
        ->and($denied)->not->toContain('chip-dot');
});

it('pops the chip of the record that was just claimed', function () {
    $carlos = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where(['first_name' => 'Carlos', 'last_name' => 'Ibanez']))->sole();

    expect($this->actingAs($this->admin)->get('/interventions/da')->getContent())->not->toContain('chip-pop');

    $this->post(route('intervention-records.claim', $carlos))->assertSessionHas('changed_record', $carlos->id);
    expect(substr_count($this->get('/interventions/da')->getContent(), 'chip-pop'))->toBe(1);
});
