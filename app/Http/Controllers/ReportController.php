<?php

namespace App\Http\Controllers;

use App\Models\DistributionCycle;
use App\Models\GeneratedReport;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Figma 340:51 (Admin) / 407:1694 (Agri Tech) / 470:2205 (Data Encoder) Report Generation (spec rule 12). */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        return view('reports.index', [
            'cycles' => DistributionCycle::orderByDesc('code')->get(),
            'currentCycleId' => DistributionCycle::current()?->id,
            'history' => $this->history($request->user())->with(['user:id,name', 'cycle:id,code,label'])
                ->latest()->latest('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function store(Request $request): StreamedResponse|RedirectResponse
    {
        $input = $request->validate([
            'distribution_cycle_id' => ['required', 'integer', Rule::exists('distribution_cycles', 'id')],
            'program' => ['required', 'string', Rule::in(array_keys(ReportCriteria::PROGRAMS))],
            'format' => ['required', 'string', Rule::in(array_keys(ReportCriteria::FORMATS))],
            'start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:start_date'],
        ], [
            'distribution_cycle_id.*' => 'Choose a distribution cycle.',
            'program.*' => 'Choose a program.',
            'format.*' => 'Choose PDF or Excel.',
            'start_date.before_or_equal' => 'Dates cannot be in the future.',
            'end_date.before_or_equal' => 'Dates cannot be in the future.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'start_date.date' => 'Enter a valid start date.',
            'end_date.date' => 'Enter a valid end date.',
        ]);

        $report = $this->reports->generate(new ReportCriteria(
            DistributionCycle::findOrFail($input['distribution_cycle_id']),
            $input['program'],
            ($input['start_date'] ?? null) ? CarbonImmutable::parse($input['start_date']) : null,
            ($input['end_date'] ?? null) ? CarbonImmutable::parse($input['end_date']) : null,
            $input['format'],
        ), $request->user());

        return Storage::disk(GeneratedReport::DISK)->download($report->path, $report->file_name);
    }

    public function download(Request $request, GeneratedReport $report): StreamedResponse|RedirectResponse
    {
        abort_unless($this->history($request->user())->whereKey($report->id)->exists(), 404);

        if (! Storage::disk(GeneratedReport::DISK)->exists($report->path)) {
            return redirect()->route('reports.index')->withErrors(['report' => 'This report file is no longer available — generate it again.']);
        }

        return Storage::disk(GeneratedReport::DISK)->download($report->path, $report->file_name);
    }

    /** The Administrator sees every generated report; other roles see their own. */
    private function history(User $user): Builder
    {
        return GeneratedReport::query()->when($user->role?->slug !== Role::ADMIN, fn (Builder $q) => $q->where('user_id', $user->id));
    }
}
