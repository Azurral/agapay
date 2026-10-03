<?php

namespace App\Http\Controllers;

use App\Models\DistributionCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Distribution cycles (no Figma frame; Administrator). Reports and distributions are tied to these scheduled
 * events (paper §1.4.1). Exactly one cycle is ongoing; cycles stay in date order.
 */
class DistributionCycleController extends Controller
{
    public function index(): View
    {
        return view('cycles.index', [
            'cycles' => DistributionCycle::withCount('records')->orderByDesc('schedule_date')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cycle = $this->save(new DistributionCycle, $this->validated($request, null));

        return redirect()->route('cycles.index')->with('status', "{$cycle->label} added.");
    }

    public function update(Request $request, DistributionCycle $cycle): RedirectResponse
    {
        $cycle = $this->save($cycle, $this->validated($request, $cycle));

        return redirect()->route('cycles.index')->with('status', "{$cycle->label} saved.");
    }

    /** @param array<string, mixed> $data */
    private function save(DistributionCycle $cycle, array $data): DistributionCycle
    {
        return DB::transaction(function () use ($cycle, $data) {
            // One ongoing cycle at a time: starting one completes the previous.
            if ($data['status'] === DistributionCycle::STATUS_ONGOING) {
                DistributionCycle::where('status', DistributionCycle::STATUS_ONGOING)
                    ->when($cycle->exists, fn ($q) => $q->whereKeyNot($cycle->id))
                    ->lockForUpdate()->get()
                    ->each(fn (DistributionCycle $other) => $other->update(['status' => DistributionCycle::STATUS_COMPLETED]));
            }

            $cycle->fill($data)->save();

            return $cycle;
        });
    }

    /** @return array{code: string, label: string, schedule_date: string, venue: ?string, status: string} */
    private function validated(Request $request, ?DistributionCycle $cycle): array
    {
        $text = fn (string $key) => is_string($request->input($key)) ? trim(preg_replace('/\s+/', ' ', $request->input($key))) : $request->input($key);
        $request->merge(['code' => is_string($code = $text('code')) ? mb_strtoupper($code) : $code, 'label' => $text('label'), 'venue' => $text('venue')]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('distribution_cycles', 'code')->ignore($cycle?->id)],
            'label' => ['required', 'string', 'max:100'],
            'schedule_date' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(DistributionCycle::STATUSES))],
        ], [
            'code.unique' => 'That cycle code is already used.',
            'code.regex' => 'Use letters, numbers and dashes only, e.g. 2026-Q4.',
            'code.required' => 'Enter a cycle code, e.g. 2026-Q4.',
            'label.required' => 'Enter a label, e.g. 2026-Q4 Wet Season.',
            'schedule_date.*' => 'Enter the schedule date.',
            'status.*' => 'Choose a status.',
        ]);
        $this->ensureDateOrder($request, $cycle);

        return $data;
    }

    /**
     * Cycles stay in date order (lists and "latest record" sort by cycle): a new cycle comes after the latest,
     * an edited one stays between its neighbours.
     */
    private function ensureDateOrder(Request $request, ?DistributionCycle $cycle): void
    {
        $date = $request->date('schedule_date');
        $before = DistributionCycle::when($cycle, fn ($q) => $q->where('id', '<', $cycle->id))->orderByDesc('id')->first();
        $after = $cycle ? DistributionCycle::where('id', '>', $cycle->id)->orderBy('id')->first() : null;

        $message = match (true) {
            $before && $date->lte($before->schedule_date) => $cycle
                ? "The date must come after {$before->code} ({$before->schedule_date->format('M j, Y')})."
                : "Schedule cycles in date order: the latest cycle, {$before->code}, is on {$before->schedule_date->format('M j, Y')}.",
            $after && $date->gte($after->schedule_date) => "The date must come before {$after->code} ({$after->schedule_date->format('M j, Y')}).",
            default => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['schedule_date' => $message]);
        }
    }
}
