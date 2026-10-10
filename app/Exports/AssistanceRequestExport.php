<?php

namespace App\Exports;

use App\Models\AssistanceRequest;
use App\Services\AssistanceRequestStats;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/** The assistance requests behind the Requests tab, with the same filters; every cell is text. */
class AssistanceRequestExport implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    /** @param array{disaster?: int|null, status?: string|null, intervention?: int|null} $filters */
    public function __construct(private readonly array $filters) {}

    public function bindValue(Cell $cell, mixed $value): bool
    {
        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function query(): Builder
    {
        return AssistanceRequestStats::query($this->filters)
            ->with(['beneficiary.barangay:id,name', 'intervention', 'disaster:id,name', 'crop:id,name', 'record:id,claim_status', 'decider:id,username'])
            ->latest()->latest('id');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Filed', 'Farmer', 'RSBSA No.', 'Barangay', 'Sitio/Purok', 'Program', 'Source', 'Quantity', 'Crisis', 'Crop', 'Reason',
            'Status', 'Decided By', 'Decision Note'];
    }

    /** @param AssistanceRequest $row */
    public function map(mixed $row): array
    {
        return [
            $row->created_at?->format('M j, Y'),
            $row->beneficiary->fullName(),
            $row->beneficiary->rsbsaDisplay(),
            (string) $row->beneficiary->barangay?->name,
            (string) $row->beneficiary->sitio,
            $row->intervention->name,
            $row->intervention->sourceLabel(),
            $row->quantity === null ? '' : rtrim(rtrim((string) $row->quantity, '0'), '.'),
            (string) $row->disaster?->name,
            (string) ($row->crop?->name ?? $row->beneficiary->crop_type),
            (string) $row->reason,
            $row->statusLabel(),
            (string) $row->decider?->username,
            (string) $row->decision_note,
        ];
    }
}
