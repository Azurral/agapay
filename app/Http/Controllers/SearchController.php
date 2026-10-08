<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** RSBSA filter value => label: farmers with a number, or without one (N/A). */
    public const RSBSA_FILTERS = ['with' => 'Has RSBSA No.', 'none' => 'N/A (no RSBSA No.)'];

    public function __invoke(Request $request): View
    {
        $term = mb_substr($request->queryText('q'), 0, 100);
        $barangay = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;
        $rsbsa = array_key_exists($request->queryText('rsbsa'), self::RSBSA_FILTERS) ? $request->queryText('rsbsa') : null;
        $hasCriteria = $term !== '' || $barangay || $rsbsa;

        $results = Beneficiary::forTable()
            ->search($term)
            ->when($barangay, fn ($q) => $q->where('barangay_id', $barangay))
            ->when($rsbsa === 'with', fn ($q) => $q->whereNotNull('rsbsa_number'))
            ->when($rsbsa === 'none', fn ($q) => $q->whereNull('rsbsa_number'))
            // An empty search lists nothing rather than the whole masterlist.
            ->when(! $hasCriteria, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate(10)->withQueryString();

        return view('search.index', [
            'results' => $results,
            'empty' => $hasCriteria ? 'No beneficiaries match your search.' : 'Type a name, RSBSA number or barangay to search.',
        ]);
    }
}
