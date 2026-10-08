<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
});

/** The Typhoon Cristina report of a seeded farmer. */
function seededReport(string $firstName): DamageReport
{
    return DamageReport::whereHas('beneficiary', fn ($q) => $q->where('first_name', $firstName))->sole();
}

it('shows the Figma list for each role', function (string $username, bool $exports) {
    $response = $this->actingAs(User::where('username', $username)->sole())->get('/damage-reports')->assertOk()
        ->assertSee('AGRICULTURAL DAMAGE REPORT')
        ->assertSeeInOrder([
            'Crisis / Crop Damage Report', 'Crisis: Typhoon Cristina', 'Barangay: All', 'Status: All', '+ New Damage Report',
            'Farmers Affected', 'Total Area Damaged (ha)', 'Production Loss (MT)', 'Est. Cost of Damage (₱)',
            'Reported Damage Records', 'Name', 'Barangay', 'Crop / Farm Loc.', 'Crop Stage', 'Damaged Area (Total/Partial)',
            'Loss (MT)', 'Cost of Damage', 'Photos', 'Status',
        ])
        ->assertSee('Generate PDF Report');

    $exports ? $response->assertSee('Export to Excel') : $response->assertDontSee('Export to Excel');
})->with([
    ['Admin_01', true],
    ['Agritech_02', false],
    ['Encoder_03', false],
]);

it('summarises the filtered reports', function () {
    $reports = DamageReport::all();

    $this->actingAs($this->admin)->get('/damage-reports')
        ->assertSeeInOrder([
            'Farmers Affected', '6',
            'Total Area Damaged (ha)', rtrim(rtrim(number_format($reports->sum(fn ($r) => $r->total_area_ha + $r->partial_area_ha), 2), '0'), '.'),
            'Production Loss (MT)', rtrim(rtrim(number_format($reports->sum('loss_mt'), 2), '0'), '.'),
            'Est. Cost of Damage (₱)', number_format($reports->sum('cost')),
        ]);
});

it('formats a row like Figma', function () {
    $this->actingAs($this->admin)->get('/damage-reports')
        ->assertSeeInOrder(['Juan Dela Cruz', 'Poblacion', 'Rice', 'Reproductive', '1.20 ha / 0.30 ha', '5.4 MT', '₱108,000', '0', 'Validated'])
        ->assertSeeInOrder(['Carlos Ibanez', 'Bontoc Ili', 'Rice', 'Maturing', '1.50 ha / 0.00 ha', '6 MT', '₱120,000', '0', 'For Validation']);
});

it('links photo counts to the viewer', function () {
    $report = seededReport('Juan');
    $report->photos()->createMany([
        ['path' => 'damage/x/a.jpg', 'original_name' => 'a.jpg', 'size' => 10],
        ['path' => 'damage/x/b.jpg', 'original_name' => 'b.jpg', 'size' => 10],
    ]);

    $this->actingAs($this->agritech)->get('/damage-reports')
        ->assertSee('2 <span class="text-muted">(view)</span>', false)
        ->assertSee(route('damage.photos.show', $report->photos()->first()), false);
});

it('filters by disaster, barangay and status', function () {
    $samoki = Barangay::where('name', 'Samoki')->value('id');

    $this->actingAs($this->admin)->get("/damage-reports?barangay={$samoki}")
        ->assertSee('Maria Santos')->assertSee('Rosa Mendez')->assertDontSee('Juan Dela Cruz');

    $this->get('/damage-reports?status=for_validation')
        ->assertSee('Carlos Ibanez')->assertSee('Lorna Reyes')->assertDontSee('Maria Santos');

    $flood = Disaster::where('name', 'Southwest Monsoon Flooding')->sole();
    DamageReport::factory()->create([
        'disaster_id' => $flood->id, 'beneficiary_id' => Beneficiary::where('first_name', 'Federico')->value('id'),
    ]);
    $this->get("/damage-reports?disaster={$flood->id}")->assertSee('Federico Wasing')->assertDontSee('Juan Dela Cruz');
    $this->get('/damage-reports?disaster=all')->assertSee('Federico Wasing')->assertSee('Juan Dela Cruz');
});

it('shows archived reports only to configurers', function () {
    seededReport('Juan')->update(['delete_reason' => 'Filed twice']);
    seededReport('Juan')->delete();

    $this->actingAs($this->admin)->get('/damage-reports')->assertDontSee('Juan Dela Cruz')->assertSee('Archived');
    $this->get('/damage-reports?status=archived')->assertSee('Juan Dela Cruz')->assertDontSee('Maria Santos');

    $this->actingAs($this->agritech)->get('/damage-reports?status=archived')
        ->assertDontSee('<option value="archived"', false)->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');
});

it('shows zeros for a disaster without reports', function () {
    $flood = Disaster::where('name', 'Southwest Monsoon Flooding')->sole();

    $this->actingAs($this->encoder)->get("/damage-reports?disaster={$flood->id}")->assertOk()
        ->assertSeeInOrder(['Farmers Affected', '0', 'Total Area Damaged (ha)', '0', 'Production Loss (MT)', '0', 'Est. Cost of Damage (₱)', '0'])
        ->assertSee('No damage reports for this filter yet.');
});

it('ignores filter values that are not choices', function () {
    $this->actingAs($this->admin)->get('/damage-reports?disaster[]=1&barangay=abc&status=hacked')->assertOk()
        ->assertSee('Juan Dela Cruz');
});

it('paginates ten rows at a time', function () {
    $typhoon = Disaster::where('name', 'Typhoon Cristina')->sole();
    DamageReport::factory()->count(9)->create(['disaster_id' => $typhoon->id, 'crop_id' => Crop::where('name', 'Corn')->value('id')]);

    $this->actingAs($this->admin)->get('/damage-reports')->assertSee('data-agapay-pagination', false)->assertSee('Showing 1–10 of 15');
});
