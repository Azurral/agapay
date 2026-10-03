<?php

namespace App\Http\Controllers;

use App\Exports\BeneficiaryListExport;
use App\Services\AuditLogger;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Figma 329:3134 Export Beneficiary List (Administrator). */
class ExportController extends Controller
{
    public function index(): View
    {
        $export = new BeneficiaryListExport;

        return view('export.index', [
            'export' => $export,
            'beneficiaries' => $export->query()->paginate(15),
        ]);
    }

    public function download(): BinaryFileResponse
    {
        $export = new BeneficiaryListExport;
        AuditLogger::record('Exported Beneficiary List', null, 'Beneficiary List', [], ['rows' => $export->query()->count()]);

        return Excel::download($export, 'agapay-beneficiaries-'.today()->format('Y-m-d').'.xlsx');
    }
}
