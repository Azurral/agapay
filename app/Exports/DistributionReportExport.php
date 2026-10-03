<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** The distribution monitoring report as a workbook: one sheet per section of ReportService::build(). */
class DistributionReportExport implements Export, WithMultipleSheets
{
    /** @param  array<string, mixed>  $data  ReportService::build() output */
    public function __construct(private readonly array $data) {}

    /** @return list<ReportSheet> */
    public function sheets(): array
    {
        $criteria = $this->data['criteria'];
        $summary = $this->data['summary'];

        return [
            new ReportSheet('Summary', ['Item', 'Value'], [
                ['Report', 'Distribution Monitoring Report'],
                ['Distribution Cycle', $criteria['cycle']],
                ['Program', $criteria['program']],
                ['Dates', $criteria['dates']],
                ['Beneficiaries', $summary['beneficiaries']],
                ['Assigned', $summary['assigned']],
                ['Claimed', $summary['claimed']],
                ['Unclaimed', $summary['unclaimed']],
            ]),
            new ReportSheet('Per Intervention', ['Program', 'Intervention', 'Unit', 'Assigned', 'Claimed', 'Unclaimed', 'Quantity Distributed'],
                array_map(fn (array $r) => [$r['source'], $r['name'], $r['unit'], $r['assigned'], $r['claimed'], $r['unclaimed'], $r['quantity']], $this->data['interventions'])),
            new ReportSheet('Per Barangay', ['Barangay', 'Beneficiaries', 'Assigned', 'Claimed', 'Unclaimed'],
                array_map(fn (array $r) => [$r['name'], $r['beneficiaries'], $r['assigned'], $r['claimed'], $r['unclaimed']], $this->data['barangays'])),
            new ReportSheet('Beneficiaries', ['Name', 'RSBSA No.', 'Barangay', 'Intervention', 'Quantity', 'Validation', 'Claim Status', 'Date Distributed'],
                array_map(fn (array $r) => array_values($r), $this->data['beneficiaries'])),
            new ReportSheet('Inventory Used', ['Item', 'Unit', 'Quantity Used'],
                array_map(fn (array $r) => [$r['item'], $r['unit_label'], $r['used']], $this->data['inventory'])),
        ];
    }
}
