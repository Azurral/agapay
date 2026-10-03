<?php

use App\Models\AuditLog;
use App\Models\DistributionCycle;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
});

function cycleInput(array $overrides = []): array
{
    return ['code' => '2026-Q4', 'label' => '2026-Q4 Wet Season', 'schedule_date' => '2026-11-15', 'venue' => 'Bontoc Municipal Gym', 'status' => 'scheduled', ...$overrides];
}

it('lists cycles for the administrator', function () {
    $this->actingAs($this->admin)->get('/distribution-cycles')->assertOk()
        ->assertSee('DISTRIBUTION CYCLES')
        ->assertSeeInOrder(['Code', 'Label', 'Schedule Date', 'Venue', 'Status', 'Records'])
        ->assertSeeInOrder(['2026-Q3', '2026-Q2', '2026-Q1'])
        ->assertSee('+ Add Cycle');

    $this->get('/interventions')->assertSee(route('cycles.index'), false);
});

it('adds a cycle that the forms and reports offer', function () {
    $this->actingAs($this->admin)->post(route('cycles.store'), cycleInput())
        ->assertRedirect(route('cycles.index'))->assertSessionHas('status', '2026-Q4 Wet Season added.');

    expect(AuditLog::where('action', 'Added Distribution Cycle')->sole()->record_label)->toBe('2026-Q4 Wet Season');
    $this->get('/reports')->assertSee('Cycle: 2026-Q4 Wet Season');
    $this->actingAs(User::where('username', 'Encoder_03')->sole())->get('/intervention-records/create')->assertSee('2026-Q4');
});

it('keeps one ongoing cycle', function () {
    $this->actingAs($this->admin)->post(route('cycles.store'), cycleInput(['status' => 'ongoing']));

    expect(DistributionCycle::where('status', 'ongoing')->pluck('code')->all())->toBe(['2026-Q4'])
        ->and(DistributionCycle::where('code', '2026-Q3')->value('status'))->toBe('completed')
        ->and(DistributionCycle::current()->code)->toBe('2026-Q4');
});

it('updates a cycle', function () {
    $q3 = DistributionCycle::where('code', '2026-Q3')->sole();

    $this->actingAs($this->admin)->put(route('cycles.update', $q3), cycleInput(['code' => '2026-Q3', 'label' => '2026-Q3 Dry Season', 'schedule_date' => '2026-08-20', 'status' => 'completed']))
        ->assertSessionHasNoErrors();

    expect($q3->fresh())->status->toBe('completed')->venue->toBe('Bontoc Municipal Gym');
});

it('orders cycles by schedule date', function () {
    $this->actingAs($this->admin)->post(route('cycles.store'), cycleInput(['schedule_date' => '2026-05-01']))
        ->assertSessionHasErrors(['schedule_date' => 'Schedule cycles in date order: the latest cycle, 2026-Q3, is on Aug 14, 2026.']);

    $q2 = DistributionCycle::where('code', '2026-Q2')->sole();
    $this->put(route('cycles.update', $q2), cycleInput(['code' => '2026-Q2', 'schedule_date' => '2026-12-01']))
        ->assertSessionHasErrors('schedule_date');
});

it('refuses a duplicate code', function () {
    $this->actingAs($this->admin)->post(route('cycles.store'), cycleInput(['code' => '2026-q3']))
        ->assertSessionHasErrors(['code' => 'That cycle code is already used.']);
});

it('is for administrators only', function (string $username) {
    $this->actingAs(User::where('username', $username)->sole())->get('/distribution-cycles')->assertForbidden();
    $this->post(route('cycles.store'), cycleInput())->assertForbidden();
})->with(['Agritech_02', 'Encoder_03']);

it('copes with an older cycle that has no schedule date', function () {
    DistributionCycle::create(['code' => '2026-X', 'label' => 'Undated cycle', 'status' => 'completed']);

    // A date already past makes the order check compare against the undated neighbour.
    $this->actingAs($this->admin)->post(route('cycles.store'), cycleInput(['schedule_date' => '2026-09-01']))->assertRedirect(route('cycles.index'));
    $this->get('/distribution-cycles')->assertOk()->assertSee('Undated cycle');
});
