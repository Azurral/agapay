<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Services\DamageReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Validate (spec rule 11), archive and restore (spec rule 3) a damage report from its detail page. */
class DamageReportActionController extends Controller
{
    public function __construct(private readonly DamageReportService $reports) {}

    public function validate(Request $request, DamageReport $report): RedirectResponse
    {
        try {
            $this->reports->validate($report, $this->text($request, 'loss_mt'), $this->text($request, 'cost'),
                $this->text($request, 'adjustment_note'), $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors(), 'damage');
        }

        return redirect()->route('damage.show', $report)->with('status', 'Damage report validated.');
    }

    public function archive(Request $request, DamageReport $report): RedirectResponse
    {
        $reason = trim((string) $this->text($request, 'delete_reason'));
        if ($reason === '' || mb_strlen($reason) > 255) {
            return back()->withErrors(['delete_reason' => 'Give a reason for archiving this report.'], 'damage');
        }

        $this->reports->archive($report, $reason, $request->user());

        return redirect()->route('damage.index')->with('status', "Damage report of {$report->beneficiary->fullName()} archived.");
    }

    public function restore(Request $request, DamageReport $report): RedirectResponse
    {
        try {
            $this->reports->restore($report, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'damage');
        }

        return redirect()->route('damage.show', $report)->with('status', 'Damage report restored.');
    }

    private function text(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
