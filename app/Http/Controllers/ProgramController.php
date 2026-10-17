<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Programs (DA and LGU interventions) the office gives out. The Administrator adds and edits them; a program is
 * deactivated rather than deleted, so its past records keep their name.
 */
class ProgramController extends Controller
{
    public function index(): View
    {
        $programs = Intervention::withCount('records')->orderBy('name')->get()->groupBy('source');

        return view('programs.index', [
            'groups' => [
                Intervention::SOURCE_DA => ['DA Programs', 'Department of Agriculture programs (an RSBSA No. is required).', $programs->get(Intervention::SOURCE_DA, collect())],
                Intervention::SOURCE_LGU => ['LGU Programs', 'Municipal programs (also for farmers without an RSBSA No.).', $programs->get(Intervention::SOURCE_LGU, collect())],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $program = Intervention::create($this->validated($request, null));

        return redirect()->route('programs.index')->with('status', "{$program->sourcedName()} added.");
    }

    public function update(Request $request, Intervention $program): RedirectResponse
    {
        $program->update($this->validated($request, $program));

        return redirect()->route('programs.index')->with('status', "{$program->sourcedName()} saved.");
    }

    /** @return array{source: string, name: string, unit: ?string, one_per_household: bool, allow_repeat: bool, is_active: bool} */
    private function validated(Request $request, ?Intervention $program): array
    {
        $squish = fn (string $key) => is_string($request->input($key)) ? Str::squish($request->input($key)) : $request->input($key);
        $request->merge(['name' => $squish('name'), 'unit' => $squish('unit') ?: null]);

        $data = $request->validate([
            'source' => ['required', 'string', Rule::in(Intervention::SOURCES)],
            'name' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) use ($request, $program) {
                $taken = Intervention::where('source', $request->input('source'))
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                    ->when($program, fn ($q) => $q->whereKeyNot($program->id))->exists();
                if ($taken) {
                    $fail('That program already exists under '.strtoupper((string) $request->input('source')).'.');
                }
            }],
            'unit' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'Enter the program name.',
            'source.*' => 'Choose DA or LGU.',
            'unit.max' => 'Keep the unit short, e.g. sack, kg, L.',
        ]);

        return [...$data,
            'one_per_household' => $request->boolean('one_per_household'),
            'allow_repeat' => $request->boolean('allow_repeat'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
