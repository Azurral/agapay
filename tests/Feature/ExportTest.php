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

it('previews only name, RSBSA number and address', function () {
    $this->actingAs($this->admin)->get('/export')->assertOk()
        ->assertSee('EXPORT BENEFICIARY LIST')
        ->assertSeeInOrder([
            'Export Beneficiary List to OMAG / DA', 'Only Name, RSBSA No., and Address are included in this export.',
            'Name', 'RSBSA Number', 'Address',
            'Ana Dela Cruz', 'RSBSA-0233', 'Purok 3, Poblacion',
            'Estrella Domogen', '(pending)', 'Sitio Maligcong',
        ])
        ->assertSee('Export as .xlsx')
        ->assertDontSee('0917-312-1274')
        ->assertDontSee('Aug 2, 2005');
});

it('downloads the xlsx with the privacy columns', function () {
    Excel::fake();

    $this->actingAs($this->admin)->get('/export/download')->assertOk();

    Excel::assertDownloaded('agapay-beneficiaries-'.today()->format('Y-m-d').'.xlsx', function (BeneficiaryListExport $export) {
        $first = $export->map($export->query()->first());

        return $export->headings() === ['Name', 'RSBSA No.', 'Address']
            && $first === ['Ana Dela Cruz', 'RSBSA-0233', 'Purok 3, Poblacion']
            && $export->map(Beneficiary::where('first_name', 'Estrella')->sole()) === ['Estrella Domogen', '(pending)', 'Sitio Maligcong'];
    });
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

it('leaves archived profiles out', function () {
    Beneficiary::where('rsbsa_number', 'RSBSA-0233')->sole()->delete();

    $this->actingAs($this->admin)->get('/export')->assertDontSee('Ana Dela Cruz');
    expect((new BeneficiaryListExport)->query()->pluck('rsbsa_number'))->not->toContain('RSBSA-0233');
});

it('audits the export', function () {
    Excel::fake();

    $this->actingAs($this->admin)->get('/export/download');

    expect(AuditLog::where('action', 'Exported Beneficiary List')->sole())
        ->user_id->toBe($this->admin->id)
        ->new_values->toBe(['rows' => Beneficiary::count()]);
});

it('is for the administrator only', function () {
    $this->actingAs(User::where('username', 'Encoder_03')->sole())->get('/export')->assertForbidden();
    $this->get('/export/download')->assertForbidden();
});
