<?php

namespace App\Http\Controllers;

use App\Exports\AssistanceRequestExport;
use App\Http\Requests\AssistanceRequestRequest;
use App\Models\AssistanceRequest;
use App\Models\Crop;
use App\Models\Disaster;
use App\Models\Intervention;
use App\Services\AssistanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Assistance requests: filed by staff for a farmer, decided by the Administrator (summary on the LGU page's Requests tab). */
class AssistanceRequestController extends Controller
{
    public function __construct(private readonly AssistanceRequestService $requests) {}

    public function create(Request $request): View
    {
        return view('assistance-requests.create', [
            'interventions' => Intervention::active()->orderBy('name')->get(['id', 'source', 'name']),
            'disasters' => Disaster::orderByDesc('occurred_on')->orderByDesc('id')->get(['id', 'name']),
            'crops' => Crop::orderBy('name')->get(['id', 'name']),
            'recent' => AssistanceRequest::with(['beneficiary', 'intervention', 'record'])
                ->where('created_by', $request->user()->id)->latest()->latest('id')->limit(8)->get(),
        ]);
    }

    public function store(AssistanceRequestRequest $request): RedirectResponse
    {
        $filed = $this->requests->file($request->validated(), $request->user());

        return redirect()->route('assistance-requests.create')->with('status', "Request filed for {$filed->beneficiary->fullName()}.");
    }

    public function approve(Request $request, AssistanceRequest $assistanceRequest): RedirectResponse
    {
        $approved = $this->requests->approve($assistanceRequest, $request->user());

        return back()->with('status', "Approved: {$approved->auditRecordLabel()} added to {$approved->record->cycle->code}.");
    }

    public function deny(Request $request, AssistanceRequest $assistanceRequest): RedirectResponse
    {
        $denied = $this->requests->deny($assistanceRequest, (string) $request->input('decision_note', ''), $request->user());

        return back()->with('status', "Denied: {$denied->auditRecordLabel()}.");
    }

    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(new AssistanceRequestExport(InterventionController::requestFilters($request)),
            'agapay-requests-'.today()->format('Y-m-d').'.xlsx');
    }
}
