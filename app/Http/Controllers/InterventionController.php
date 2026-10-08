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

    /** Figma 344:236 / 344:531: archived records of one source, newest deletion first. */
    public function archived(Request $request, string $source): View
    {
        $q = mb_substr($request->queryText('q'), 0, 100);

        return view('interventions.archived', [
            'source' => $source,
            'records' => InterventionRecord::onlyTrashed()
                ->ofSource($source)
                ->with(['beneficiary', 'intervention', 'cycle', 'deleter:id,username'])
                ->when($q !== '', fn (Builder $query) => $query->whereHas('beneficiary', fn (Builder $b) => $b->search($q)))
                ->orderByDesc('deleted_at')->orderByDesc('id')
                ->paginate(15)->withQueryString(),
        ]);
    }

    public function list(Request $request, string $source): View
    {
        // The LGU page opens on every farmer (municipal list); its program records are the second tab.
        if ($source === Intervention::SOURCE_LGU && $request->queryText('tab') !== 'records') {
            return $this->lguBeneficiaries($request);
        }

        $interventions = Intervention::where('source', $source)->orderBy('name')->pluck('name', 'id');
        $interventionId = ctype_digit($request->queryText('intervention')) ? (int) $request->queryText('intervention') : null;
        $barangayId = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;
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

    /** LGU Beneficiaries: all active farmers with their address, farm area and number of crisis reports. */
    private function lguBeneficiaries(Request $request): View
    {
        $name = mb_substr($request->queryText('name'), 0, 100);
        $barangayId = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;

        return view('interventions.lgu-beneficiaries', [
            'beneficiaries' => Beneficiary::query()
                ->with('barangay:id,name')
                ->withCount('damageReports')
                ->when($name !== '', fn (Builder $q) => $q->search($name))
                ->when($barangayId, fn (Builder $q) => $q->where('barangay_id', $barangayId))
                ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
                ->paginate(15)->withQueryString(),
            'barangays' => Barangay::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
