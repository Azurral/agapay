<?php

use App\Exports\BeneficiaryListExport;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
});

/** The export rows of one beneficiary. */
function exportRows(string $first, string $last): array
{
    return (new BeneficiaryListExport)->map(Beneficiary::where(['first_name' => $first, 'last_name' => $last])->sole());
}

/** "Typhoon Cristina (₱108,000.00)" for a seeded farmer's report. */
function crisisText(string $first, string $last): string
{
    $report = Beneficiary::where(['first_name' => $first, 'last_name' => $last])->sole()->damageReports()->with('disaster')->sole();

    return $report->disaster->name.' (₱'.number_format((float) $report->cost, 2).')';
}

it('previews the beneficiary list with programs, claims, amounts and crises', function () {
    $this->actingAs($this->admin)->get('/export')->assertOk()
        ->assertSee('EXPORT BENEFICIARY LIST')
        ->assertSee('Export Beneficiary List')
        ->assertDontSee('OMAG / DA')
        ->assertSee('Birthdates and contact numbers are left out.')
        ->assertSeeInOrder(['Name', 'RSBSA No.', 'Address', 'Program', 'Cycle', 'Claim Status', 'Amount', 'Crises'])
        ->assertSeeInOrder(['Juan Dela Cruz', 'RSBSA-0231', 'Purok 3, Poblacion', 'Certified Rice Seeds', '2026-Q3', 'Claimed', '2 sacks', crisisText('Juan', 'Dela Cruz')], false)
        ->assertSee('Export as .xlsx')
        ->assertDontSee('0917-312-1274');
});

it('writes one row per program given, and one blank row for farmers without one', function () {
    expect(exportRows('Juan', 'Dela Cruz'))->toBe([
        ['Juan Dela Cruz', 'RSBSA-0231', 'Purok 3, Poblacion', 'Certified Rice Seeds', 'DA', '2026-Q3', 'Claimed', '2 sacks', crisisText('Juan', 'Dela Cruz')],
    ])
        ->and(exportRows('Ana', 'Gomez'))->toBe([
            ['Ana Gomez', 'N/A', 'Purok 2, Poblacion', 'Municipal Cash Subsidy', 'LGU', '2026-Q3', 'Not Claimable', '', ''],
        ])
        ->and(exportRows('Ana', 'Dela Cruz'))->toBe([
            ['Ana Dela Cruz', 'RSBSA-0233', 'Purok 3, Poblacion', '', '', '', '', '', ''],
        ]);
});

it('downloads the xlsx with the new headings', function () {
    Excel::fake();

    $this->actingAs($this->admin)->get('/export/download')->assertOk();

    Excel::assertDownloaded('agapay-beneficiaries-'.today()->format('Y-m-d').'.xlsx', fn (BeneficiaryListExport $export) => $export->headings() === [
        'Name', 'RSBSA No.', 'Address', 'Program', 'Source', 'Cycle', 'Claim Status', 'Amount', 'Crises',
    ]);
});

it('writes every value as text so nothing runs as a formula', function () {
    Beneficiary::factory()->create(['first_name' => '=HYPERLINK("http://x","Click")', 'last_name' => 'Aaa', 'rsbsa_number' => '05170100100012']);
    $path = tempnam(sys_get_temp_dir(), 'agapay').'.xlsx';
    file_put_contents($path, Excel::raw(new BeneficiaryListExport, Maatwebsite\Excel\Excel::XLSX));

    $sheet = IOFactory::load($path)->getActiveSheet();

    expect($sheet->getCell('A2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('A2')->getValue())->toStartWith('=HYPERLINK')
        ->and($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('B2')->getValue())->toBe('05170100100012');
});

it('leaves archived profiles, records and crisis reports out', function () {
    Beneficiary::where('rsbsa_number', 'RSBSA-0233')->sole()->delete();
    $juan = Beneficiary::where('rsbsa_number', 'RSBSA-0231')->sole();
    $juan->damageReports()->sole()->delete();
    $juan->interventionRecords()->sole()->delete();

    $this->actingAs($this->admin)->get('/export')->assertDontSee('Ana Dela Cruz');
    expect((new BeneficiaryListExport)->query()->pluck('rsbsa_number'))->not->toContain('RSBSA-0233')
        ->and(exportRows('Juan', 'Dela Cruz'))->toBe([['Juan Dela Cruz', 'RSBSA-0231', 'Purok 3, Poblacion', '', '', '', '', '', '']]);
});

it('audits the export with its row count', function () {
    Excel::fake();

    $this->actingAs($this->admin)->get('/export/download');

    $export = new BeneficiaryListExport;
    $rows = $export->query()->get()->sum(fn (Beneficiary $b) => count($export->map($b)));
    expect(AuditLog::where('action', 'Exported Beneficiary List')->sole())
        ->user_id->toBe($this->admin->id)
        ->new_values->toBe(['rows' => $rows]);
});

it('is for the administrator only', function () {
    $this->actingAs(User::where('username', 'Encoder_03')->sole())->get('/export')->assertForbidden();
    $this->get('/export/download')->assertForbidden();
});
