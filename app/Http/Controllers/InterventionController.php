<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InterventionController extends Controller
{
    public function index(): View
    {
        return view('interventions.index');
    }

    public function list(Request $request, string $source): View
    {
        $interventions = Intervention::where('source', $source)->orderBy('name')->pluck('name', 'id');
        $interventionId = ctype_digit($request->queryText('intervention')) ? (int) $request->queryText('intervention') : null;
        $barangayId = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;
        $registration = $source === Intervention::SOURCE_LGU ? $request->queryText('registration') : '';
        $name = mb_substr($request->queryText('name'), 0, 100);
        $rsbsa = mb_substr($request->queryText('rsbsa'), 0, 50);

        $records = InterventionRecord::query()
            ->ofSource($source)
            ->with(['beneficiary.barangay:id,name', 'intervention', 'cycle'])
            // Records of archived beneficiaries stay out of the working lists.
            ->whereIn('beneficiary_id', Beneficiary::select('id'))
            ->when($name !== '', fn (Builder $q) => $q->whereHas('beneficiary', fn (Builder $b) => $b->search($name)))
            ->when($rsbsa !== '', fn (Builder $q) => $q->whereHas('beneficiary', fn (Builder $b) => $b->whereRaw(
                "LOWER(rsbsa_number) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($rsbsa)).'%']
            )))
            ->when($barangayId, fn (Builder $q) => $q->whereHas('beneficiary', fn (Builder $b) => $b->where('barangay_id', $barangayId)))
            ->when($interventionId, fn (Builder $q) => $q->where('intervention_id', $interventionId))
            ->when(isset(Beneficiary::REGISTRATION_FILTERS[$registration]), fn (Builder $q) => $q->whereHas(
                'beneficiary', fn (Builder $b) => $b->whereIn('rsbsa_status', Beneficiary::REGISTRATION_FILTERS[$registration])
            ))
            ->orderByDesc('distribution_cycle_id')->orderBy('id')
            ->paginate(15)->withQueryString();

        $user = $request->user();

        return view('interventions.list', [
            'source' => $source,
            'records' => $records,
            'interventions' => $interventions,
            'barangays' => Barangay::orderBy('name')->pluck('name', 'id'),
            // Figma: the Administrator sees read-only chips and archives; others work the dropdowns.
            'variant' => $user->role?->slug === Role::ADMIN ? 'chips' : 'dropdowns',
            'canArchive' => $user->role?->slug === Role::ADMIN && $user->can('interventions.archive'),
        ]);
    }
}
