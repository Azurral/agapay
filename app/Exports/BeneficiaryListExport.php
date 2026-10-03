<?php

namespace App\Exports;

use App\Models\Beneficiary;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Spec rule 10: the list sent to OMAG / DA carries only Name, RSBSA No. and Address. */
class BeneficiaryListExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return Beneficiary::query()->with('barangay:id,name')->orderBy('last_name')->orderBy('first_name')->orderBy('id');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Name', 'RSBSA No.', 'Address'];
    }

    /**
     * @param  Beneficiary  $row
     * @return array{0: string, 1: string, 2: string}
     */
    public function map(mixed $row): array
    {
        return [$row->fullName(), $row->rsbsa_number ?? '(pending)', self::address($row)];
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
