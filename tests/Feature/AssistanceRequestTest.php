<?php

use App\Models\AssistanceRequest;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\Permission;
use App\Models\User;
use App\Services\AssistanceRequestStats;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\GpsJpeg;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    config(['agapay.geo.check_photos' => true]);   // the request photos are checked as on the real copies
});

function farmer(string $first, string $last): Beneficiary
{
    return Beneficiary::where(['first_name' => $first, 'last_name' => $last])->sole();
}

/** A phone photo taken at the farmer's farm (GPS on), at the given time. */
function farmPhoto(Beneficiary $farmer, ?string $takenAt = '2026-07-21 09:00:00', float $north = 0.0, string $name = 'damage.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, GpsJpeg::bytes((float) $farmer->farm_latitude + $north, (float) $farmer->farm_longitude, takenAt: $takenAt));
}

/** A request after Typhoon Cristina; a crisis request carries a photo taken at the farm unless the test gives its own. */
function requestInput(array $overrides = []): array
{
    $input = [
        'beneficiary_id' => farmer('Ana', 'Dela Cruz')->id,
        'source' => 'lgu',
        'intervention_id' => program('lgu', 'Emergency Seedlings')->id,
        'quantity' => '5',
        'disaster_id' => Disaster::where('name', 'Typhoon Cristina')->value('id'),
        'crop_id' => '',
        'reason' => 'Seedbed washed out',
        ...$overrides,
    ];
    if (! array_key_exists('photos', $overrides) && $input['disaster_id'] && ($farmer = Beneficiary::find($input['beneficiary_id']))) {
        $input['photos'] = [farmPhoto($farmer)];
    }

    return $input;
}

it('lets encoders and agri techs file a request that waits for the administrator', function (string $username) {
    $user = User::where('username', $username)->sole();
    $this->actingAs($user)->get('/assistance-requests/create')->assertOk()
        ->assertSeeInOrder(['New Assistance Request', 'Beneficiary:', 'Program:', 'Intervention Type:', 'Quantity', 'Crisis:', 'Crop:', 'Reason', 'File Request']);

    $this->post('/assistance-requests', requestInput())
        ->assertRedirect('/assistance-requests/create')
        ->assertSessionHas('status', 'Request filed for Ana Dela Cruz.');

    $request = AssistanceRequest::latest('id')->firstOrFail();
    expect($request)->status->toBe('pending')->created_by->toBe($user->id)->reason->toBe('Seedbed washed out')
        ->and(AuditLog::where('action', 'Added Assistance Request')->where('record_label', 'Ana Dela Cruz - Emergency Seedlings')->exists())->toBeTrue();
    $this->get('/assistance-requests/create')->assertSeeInOrder(['Your recent requests', 'Ana Dela Cruz', 'Emergency Seedlings', 'Pending']);
})->with(['Encoder_03', 'Agritech_02']);

it('checks the request form', function () {
    $this->actingAs($this->encoder)->post('/assistance-requests', requestInput(['beneficiary_id' => '', 'intervention_id' => '', 'quantity' => 'lots']))
        ->assertSessionHasErrors([
            'beneficiary_id' => 'Choose a beneficiary from the search results.',
            'intervention_id' => 'Choose what the farmer is asking for.',
            'quantity' => 'Enter the quantity as a number, e.g. 2.',
        ]);

    expect(AssistanceRequest::where('reason', 'Seedbed washed out')->exists())->toBeFalse();
});

it('keeps filing behind its permission', function () {
    $this->encoder->role->permissions()->detach(Permission::where('slug', 'requests.create')->value('id'));

    $this->actingAs($this->encoder->fresh())->get('/assistance-requests/create')->assertForbidden();
    $this->post('/assistance-requests', requestInput())->assertForbidden();
});

it('approves a request into a program record for the current cycle', function () {
    $this->actingAs($this->encoder)->post('/assistance-requests', requestInput());
    $request = AssistanceRequest::latest('id')->firstOrFail();

    $this->actingAs($this->admin)->post(route('assistance-requests.approve', $request))
        ->assertSessionHas('status', 'Approved: Ana Dela Cruz - Emergency Seedlings added to 2026-Q3.');

    $record = $request->fresh()->record;
    expect($request->fresh())->status->toBe('approved')->decided_by->toBe($this->admin->id)->decided_at->not->toBeNull()
        ->and($record)->beneficiary_id->toBe(farmer('Ana', 'Dela Cruz')->id)
        ->distribution_cycle_id->toBe(DistributionCycle::current()->id)
        ->and((float) $record->quantity)->toBe(5.0)
        ->and(AuditLog::where('action', 'Approved Assistance Request')->exists())->toBeTrue();

    $record->update(['claim_status' => InterventionRecord::CLAIM_CLAIMED, 'date_distributed' => today()]);
    expect($request->fresh()->statusLabel())->toBe('Released');
});

it('keeps a request pending when the program rules refuse it', function () {
    $pedro = farmer('Pedro', 'Reyes');   // no RSBSA number
    $this->actingAs($this->encoder)->post('/assistance-requests', requestInput([
        'beneficiary_id' => $pedro->id, 'source' => 'da', 'intervention_id' => program('da', 'Certified Rice Seeds')->id,
    ]));
    $request = AssistanceRequest::latest('id')->firstOrFail();

    $this->actingAs($this->admin)->from('/interventions/lgu?tab=requests')->post(route('assistance-requests.approve', $request))
        ->assertSessionHasErrors(['request' => "Pedro Reyes has no RSBSA No. DA programs need one; LGU programs don't."]);

    expect($request->fresh()->status)->toBe('pending');
});

it('denies a request only with a reason, once', function () {
    $request = AssistanceRequest::where('status', 'pending')->firstOrFail();

    $this->actingAs($this->admin)->post(route('assistance-requests.deny', $request), ['decision_note' => ' '])
        ->assertSessionHasErrors(['decision_note' => 'Say why the request is denied.']);
    $this->post(route('assistance-requests.deny', $request), ['decision_note' => 'Not a farmer in this barangay'])->assertSessionHasNoErrors();

    expect($request->fresh())->status->toBe('denied')->decision_note->toBe('Not a farmer in this barangay');
    $this->post(route('assistance-requests.approve', $request))->assertSessionHasErrors(['request' => 'This request was already decided.']);
});

it('lets only the administrator decide', function () {
    $request = AssistanceRequest::where('status', 'pending')->firstOrFail();

    $this->actingAs($this->agritech)->post(route('assistance-requests.approve', $request))->assertForbidden();
    $this->actingAs($this->encoder)->post(route('assistance-requests.deny', $request), ['decision_note' => 'x'])->assertForbidden();
});

it('seeds requests across barangays and crises', function () {
    expect(AssistanceRequest::count())->toBe(15)
        ->and(AssistanceRequest::where('status', 'pending')->count())->toBe(9)
        ->and(AssistanceRequest::where('status', 'approved')->count())->toBe(3)
        ->and(AssistanceRequest::where('status', 'denied')->count())->toBe(3);
});

it('counts requests per barangay, sitio, crisis and crop', function () {
    $stats = app(AssistanceRequestStats::class)->build([]);
    $poblacion = collect($stats['barangays'])->firstWhere('name', 'Poblacion');
    $typhoon = collect($stats['crises'])->firstWhere('name', 'Typhoon Cristina');
    $typhoonId = Disaster::where('name', 'Typhoon Cristina')->value('id');
    $farmers = AssistanceRequest::where('disaster_id', $typhoonId)->distinct()->pluck('beneficiary_id');
    $damaged = DamageReport::where('disaster_id', $typhoonId)->whereIn('beneficiary_id', $farmers)
        ->get()->sum(fn ($r) => (float) $r->total_area_ha + (float) $r->partial_area_ha);

    expect($stats['totals'])->toBe(['requests' => 15, 'pending' => 9, 'approved' => 3, 'denied' => 3, 'released' => 1])
        ->and($poblacion)->toMatchArray(['requests' => 6, 'farmers' => 5, 'pending' => 5, 'approved' => 0, 'denied' => 1, 'top' => 'Emergency Seedlings (3)'])
        ->and($typhoon)->toMatchArray(['requests' => 8, 'farmers' => 8])
        ->and(round($typhoon['damaged_ha'], 2))->toBe(round($damaged, 2))
        ->and(collect($stats['sitios'])->firstWhere('name', 'Purok 3 · Poblacion')['requests'])->toBe(4)
        ->and(collect($stats['crops'])->pluck('name'))->toContain('Rice');

    $filtered = app(AssistanceRequestStats::class)->build(['status' => 'denied']);
    expect($filtered['totals']['requests'])->toBe(3)
        ->and(collect($filtered['barangays'])->sum('requests'))->toBe(3);
});

it('shows the requests tab with summaries and decision buttons', function () {
    $this->actingAs($this->admin)->get('/interventions/lgu?tab=requests')->assertOk()
        ->assertSee('href="'.route('interventions.lgu', ['tab' => 'requests']).'"', false)
        ->assertSeeInOrder(['Assistance Requests', 'Total Requests', '15', 'Pending', '9', 'Approved', '3', 'Denied', '3', 'Released', '1'])
        ->assertSeeInOrder(['By Barangay', 'Poblacion', 'By Sitio/Purok', 'By Crisis', 'Typhoon Cristina', 'By Crop', 'Requests'])
        ->assertSee('Approve')->assertSee('name="decision_note"', false)
        ->assertSee('+ New Request')->assertSee('Export as .xlsx');

    $this->actingAs($this->agritech)->get('/interventions/lgu?tab=requests')->assertOk()
        ->assertDontSee('name="decision_note"', false);

    $this->actingAs($this->admin)->get('/interventions/lgu?tab=requests&status=denied')
        ->assertSee('Federico Wasing')->assertDontSee('Lorna Reyes');
});

it('downloads the requests as Excel', function () {
    Excel::fake();

    $this->actingAs($this->agritech)->get(route('assistance-requests.export', ['status' => 'pending']))->assertOk();

    Excel::assertDownloaded('agapay-requests-'.today()->format('Y-m-d').'.xlsx', fn ($export) => $export->headings() === [
        'Filed', 'Farmer', 'RSBSA No.', 'Barangay', 'Sitio/Purok', 'Program', 'Source', 'Quantity', 'Crisis', 'Crop', 'Reason',
        'Status', 'Decided By', 'Decision Note',
    ] && $export->query()->count() === 9);
});

it('needs a photo of the damage when the request is about a crisis, and none otherwise', function () {
    $this->actingAs($this->encoder)->post('/assistance-requests', requestInput(['photos' => []]))
        ->assertSessionHasErrors(['photos' => "Attach at least one photo of the damage, taken at the farm with the phone's location on."]);

    $this->post('/assistance-requests', requestInput(['disaster_id' => '', 'photos' => []]))->assertSessionHasNoErrors();
    expect(AssistanceRequest::latest('id')->first()->photos)->toHaveCount(0);
});

it("refuses a crisis photo without the phone's location or taken away from the farm", function () {
    $ana = farmer('Ana', 'Dela Cruz');
    $noGps = UploadedFile::fake()->createWithContent('nogps.jpg', GpsJpeg::bytes(null, null, takenAt: '2026-07-21 09:00:00'));

    $this->actingAs($this->encoder)->post('/assistance-requests', requestInput(['photos' => [$noGps]]))
        ->assertSessionHasErrors(['photos' => "nogps.jpg has no GPS location. Take the photo with the phone's location turned on."]);
    $this->post('/assistance-requests', requestInput(['photos' => [farmPhoto($ana, north: 0.05, name: 'far.jpg')]]))
        ->assertSessionHasErrors('photos');
    expect(session('errors')->getBag('default')->first('photos'))->toContain('far.jpg was taken 5.')->toContain("from Ana Dela Cruz's farm");
});

it('refuses a crisis photo with no date taken, a future date, or a date before the crisis', function (?string $takenAt, string $message) {
    $this->actingAs($this->encoder)->post('/assistance-requests', requestInput(['photos' => [farmPhoto(farmer('Ana', 'Dela Cruz'), $takenAt, name: 'field.jpg')]]))
        ->assertSessionHasErrors(['photos' => $message]);
    expect(AssistanceRequest::where('reason', 'Seedbed washed out')->exists())->toBeFalse();
})->with([
    'no date' => [null, "field.jpg has no date taken. Use the phone's own camera, not a screenshot or an edited copy."],
    'future' => ['2099-01-01 08:00:00', "field.jpg is dated Jan 1, 2099, which is later than today. Check the phone's date and time."],
    'before the crisis' => ['2026-07-01 08:00:00', 'field.jpg was taken on Jul 1, 2026, before Typhoon Cristina (Jul 20, 2026). Attach photos taken after the crisis.'],
]);

it('keeps the photos with the request, where and when each was taken, for the administrator to see', function () {
    Storage::fake('local');
    $this->actingAs($this->encoder)->post('/assistance-requests', requestInput(['photos' => [farmPhoto(farmer('Ana', 'Dela Cruz'), '2026-07-22 10:15:00')]]))
        ->assertSessionHasNoErrors();

    $request = AssistanceRequest::latest('id')->firstOrFail();
    $photo = $request->photos()->sole();
    expect($photo->taken_at->format('Y-m-d H:i'))->toBe('2026-07-22 10:15')
        ->and($photo->latitude)->not->toBeNull()
        ->and($photo->distance_m)->toBe(0);
    Storage::disk('local')->assertExists($photo->path);

    $this->actingAs($this->admin)->get('/interventions/lgu?tab=requests')->assertOk()
        ->assertSee(route('assistance-requests.photo', [$request, $photo]), false);
    $this->get(route('assistance-requests.photo', [$request, $photo]))->assertOk();
});
