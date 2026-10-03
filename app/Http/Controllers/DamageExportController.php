<?php

namespace App\Http\Controllers;

use App\Exports\DamageReportExport;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DamageReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** "Export to Excel" and "Generate PDF Report" on the damage list (Figma 423:214 / 423:216), for the current filters. */
class DamageExportController extends Controller
{
    public function excel(Request $request): BinaryFileResponse
    {
        $filters = DamageReportFilters::fromRequest($request);
        AuditLogger::record('Exported Damage Reports', null, 'Damage Reports', [], ['filters' => $filters->describe(), 'rows' => $filters->query()->count()]);

        return Excel::download(new DamageReportExport($filters), self::fileName($filters, 'xlsx'));
    }

    public function pdf(Request $request): Response
    {
        // DomPDF needs ~0.4 MB per row; the row cap in pdfData() keeps a big event within this.
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $filters = DamageReportFilters::fromRequest($request);
        $data = self::pdfData($filters, $request->user());
        AuditLogger::record('Generated Damage Report PDF', null, 'Damage Reports', [], ['filters' => $filters->describe(), 'rows' => $data['totalRows']]);

        return Pdf::loadView('damage.pdf', $data)->setPaper('a4', 'landscape')->download(self::fileName($filters, 'pdf'));
    }

    /** @return array<string, mixed> the damage.pdf view data */
    public static function pdfData(DamageReportFilters $filters, User $user): array
    {
        $query = $filters->query();
        $maxRows = (int) config('agapay.damage_pdf_max_rows', 1000);

        return [
            'filters' => $filters,
            'totalRows' => (clone $query)->count(),
            'maxRows' => $maxRows,
            'summary' => (clone $query)->toBase()->selectRaw(
                'COUNT(DISTINCT beneficiary_id) AS farmers, COALESCE(SUM(total_area_ha + partial_area_ha), 0) AS area,'
                .' COALESCE(SUM(loss_mt), 0) AS loss, COALESCE(SUM(cost), 0) AS cost'
            )->first(),
            // The summary covers every report; the table stops at the cap (Excel has the full list).
            'reports' => $query->with(['beneficiary', 'barangay:id,name', 'crop:id,name'])->orderBy('barangay_id')->oldest()->limit($maxRows)->get(),
            'generatedBy' => $user->name,
            'generatedAt' => now(),
        ];
    }

    /** agapay-damage-typhoon-cristina-2026-10-05.xlsx */
    private static function fileName(DamageReportFilters $filters, string $extension): string
    {
        return 'agapay-damage-'.(Str::slug((string) $filters->disaster()?->name) ?: 'all').'-'.today()->format('Y-m-d').".{$extension}";
    }
}
