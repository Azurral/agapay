<?php

namespace App\Exports;

use App\Models\Beneficiary;
use App\Models\DamageReport;
use App\Models\InterventionRecord;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * The beneficiary list: one row per program given (name, RSBSA No., address, program, claim status, amount and the
 * farmer's crises). Birthdates and contact numbers stay out (Data Privacy Act).
 */
class BeneficiaryListExport implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    /**
     * Every cell is text: a name typed as "=HYPERLINK(…)" must not become a live formula in the file sent to DA,
     * and an all-digit RSBSA No. must not turn into a number shown as 5.17E+13.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function query(): Builder
    {
        return Beneficiary::query()
            ->with([
                'barangay:id,name',
                'interventionRecords' => fn ($q) => $q->with(['intervention', 'cycle'])->orderBy('distribution_cycle_id')->orderBy('id'),
                'damageReports' => fn ($q) => $q->with('disaster')->orderBy('id'),
            ])
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Name', 'RSBSA No.', 'Address', 'Program', 'Source', 'Cycle', 'Claim Status', 'Amount', 'Crises'];
    }

    /**
     * One row per active program record; a farmer with none gets one row with the program columns blank.
     *
     * @param  Beneficiary  $row
     * @return list<list<string>>
     */
    public function map(mixed $row): array
    {
        $person = [$row->fullName(), $row->rsbsaDisplay(), self::address($row)];
        $crises = $row->damageReports
            ->map(fn (DamageReport $r) => $r->disaster->name.' (₱'.number_format((float) $r->cost, 2).')')
            ->join('; ');

        if ($row->interventionRecords->isEmpty()) {
            return [[...$person, '', '', '', '', '', $crises]];
        }

        return $row->interventionRecords->map(fn (InterventionRecord $r) => [
            ...$person,
            $r->intervention->name,
            $r->intervention->sourceLabel(),
            $r->cycle->code,
            ! $r->isClaimed() && in_array($r->validation_status, ReportService::NOT_CLAIMABLE, true) ? 'Not Claimable' : $r->claimLabel(),
            $r->quantity === null ? '' : $r->quantityDisplay(),
            $crises,
        ])->values()->all();
    }

    /** "Purok 3, Poblacion"; the address alone when it already names the barangay ("Sitio Maligcong"). */
    public static function address(Beneficiary $beneficiary): string
    {
        $barangay = (string) $beneficiary->barangay?->name;
        $address = trim((string) $beneficiary->address);

        if ($address === '' || $barangay === '') {
            return $address ?: $barangay;
        }

        return str_contains(mb_strtolower($address), mb_strtolower($barangay)) ? $address : "{$address}, {$barangay}";
    }
}
