<?php

namespace App\Exports;

use App\Models\DamageReport;
use App\Support\DamageReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/** "Export to Excel" on the damage list (Figma 423:214): the filtered reports with every recorded field. */
class DamageReportExport implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private readonly DamageReportFilters $filters) {}

    public function query(): Builder
    {
        return $this->filters->query()
            ->with(['disaster:id,name', 'beneficiary', 'barangay:id,name', 'crop:id,name', 'reporter:id,name', 'validator:id,name'])
            ->withCount('photos')
            ->latest()->latest('id');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Crisis', 'Farmer', 'RSBSA No.', 'Barangay', 'Crop', 'Farm Location', 'Crop Stage', 'Total Area (ha)', 'Partial Area (ha)',
            'Loss (MT)', 'Cost (₱)', 'Latitude', 'Longitude', 'Photos', 'Status', 'Reported By', 'Date Reported', 'Validated By', 'Validated On',
        ];
    }

    /**
     * @param  DamageReport  $row
     * @return list<string|int|float>
     */
    public function map(mixed $row): array
    {
        return [
            $row->disaster->name,
            $row->beneficiary->fullName(),
            $row->beneficiary->rsbsaDisplay(),
            $row->barangay->name,
            $row->crop->name,
            (string) $row->farm_location,
            $row->stageLabel(),
            (float) $row->total_area_ha,
            (float) $row->partial_area_ha,
            (float) $row->loss_mt,
            (float) $row->cost,
            $row->latitude !== null ? (float) $row->latitude : '',
            $row->longitude !== null ? (float) $row->longitude : '',
            (int) $row->photos_count,
            $row->trashed() ? 'Archived' : $row->statusLabel(),
            (string) $row->reporter?->name,
            $row->created_at->format('Y-m-d H:i'),
            (string) $row->validator?->name,
            (string) $row->validated_at?->format('Y-m-d H:i'),
        ];
    }

    /** Figures stay numbers for sums in Excel; every text cell is written as text, so nothing runs as a formula. */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
        } else {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }

        return true;
    }
}
