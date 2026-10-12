<?php

use App\Models\Beneficiary;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
});

it('lists only the farmers who still need an RSBSA No., and counts them', function () {
    $without = Beneficiary::whereNull('rsbsa_number')->orderBy('last_name')->get();
    expect($without)->not->toBeEmpty();

    $response = $this->actingAs($this->admin)->get('/interventions/lgu?rsbsa=none')->assertOk()
        ->assertSee("{$without->count()} farmers without an RSBSA No.");
    foreach ($without as $b) {
        $response->assertSee($b->fullName());
    }
    $response->assertDontSee(Beneficiary::whereNotNull('rsbsa_number')->first()->rsbsa_number);
});

it('lists only the farmers who have an RSBSA No.', function () {
    $this->actingAs($this->admin)->get('/interventions/lgu?rsbsa=with')->assertOk()
        ->assertDontSee('>N/A<', false)
        ->assertSee(Beneficiary::whereNotNull('rsbsa_number')->orderBy('last_name')->first()->rsbsa_number);
});

it('shows the RSBSA filter next to the other filters and keeps it with the barangay filter', function () {
    $this->actingAs($this->admin)->get('/interventions/lgu')->assertOk()
        ->assertSeeInOrder(['Search Name...', 'Barangay', 'RSBSA'])
        ->assertSee('No RSBSA No. yet');
});

it('ignores a forged RSBSA filter value', function (string $query) {
    $this->actingAs($this->admin)->get('/interventions/lgu?'.$query)->assertOk();
})->with(['rsbsa=maybe', 'rsbsa[]=none']);
