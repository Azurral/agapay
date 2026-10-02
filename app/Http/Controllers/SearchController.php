<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public const STATUSES = [
        Beneficiary::RSBSA_PENDING, Beneficiary::RSBSA_VALIDATED, Beneficiary::RSBSA_ENDORSED,
        Beneficiary::RSBSA_REGISTERED, Beneficiary::RSBSA_RETURNED, Beneficiary::RSBSA_REJECTED,
    ];

    public function __invoke(Request $request): View
    {
        $term = mb_substr($request->queryText('q'), 0, 100);
        $barangay = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;
        $status = in_array($request->queryText('rsbsa_status'), self::STATUSES, true) ? $request->queryText('rsbsa_status') : null;
        $hasCriteria = $term !== '' || $barangay || $status;

        $results = Beneficiary::forTable()
            ->search($term)
            ->when($barangay, fn ($q) => $q->where('barangay_id', $barangay))
            ->when($status, fn ($q) => $q->where('rsbsa_status', $status))
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
