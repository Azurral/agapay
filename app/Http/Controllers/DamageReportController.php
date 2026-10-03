<?php

namespace App\Http\Controllers;

use App\Http\Requests\DamageReportRequest;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use App\Services\DamageReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Figma 329:2978 / 407:1554 / 470:1863 damage list and 423:786 / 423:306 New Damage Report (spec rule 11). */
class DamageReportController extends Controller
{
    public function __construct(private readonly DamageReportService $reports) {}

    public function create(Request $request): View
    {
        return $this->form(null, $request);
    }

    public function store(DamageReportRequest $request): RedirectResponse
    {
        $report = $this->reports->file($request->validated(), $request->file('photos', []), $request->user());

        return redirect()->route('damage.show', $report)->with('status', "Damage report filed for {$report->beneficiary->fullName()}.");
    }

    public function show(Request $request, DamageReport $report): View
    {
        return view('damage.show', [
            'report' => $report->load(['disaster', 'beneficiary', 'barangay', 'crop', 'photos', 'reporter', 'validator']),
            'canEdit' => self::canEdit($request->user(), $report),
        ]);
    }

    public function edit(Request $request, DamageReport $report): View
    {
        abort_unless(self::canEdit($request->user(), $report), 403);

        return $this->form($report, $request);
    }

    public function update(DamageReportRequest $request, DamageReport $report): RedirectResponse
    {
        abort_unless(self::canEdit($request->user(), $report), 403);

        $this->reports->update($report, $request->validated(), $request->file('photos', []),
            array_map('intval', $request->validated('remove_photos') ?? []), $request->user());

        return redirect()->route('damage.show', $report)->with('status', 'Damage report saved.');
    }

    /** Unvalidated reports can be corrected by whoever filed them, or by an Administrator. */
    public static function canEdit(User $user, DamageReport $report): bool
    {
        return ! $report->isValidated() && ! $report->trashed()
            && ($report->reported_by === $user->id || $user->can('damage.configure'));
    }

    private function form(?DamageReport $report, Request $request): View
    {
        return view('damage.form', [
            'report' => $report?->load(['beneficiary', 'photos']),
            'disasters' => Disaster::orderByDesc('occurred_on')->orderByDesc('id')->get(),
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
            'crops' => Crop::orderBy('name')->get(),
            'tooLarge' => $request->boolean('too_large'),
        ]);
    }
}
