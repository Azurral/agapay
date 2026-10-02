<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BeneficiaryController extends Controller
{
    public function index(Request $request): View
    {
        $name = mb_substr($request->queryText('name'), 0, 100);
        $rsbsa = mb_substr($request->queryText('rsbsa'), 0, 50);
        $barangay = ctype_digit($request->queryText('barangay')) ? (int) $request->queryText('barangay') : null;

        $beneficiaries = Beneficiary::forTable()
            ->when($name !== '', fn ($q) => $q->search($name))
            ->when($rsbsa !== '', fn ($q) => $q->whereRaw("LOWER(rsbsa_number) LIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($rsbsa)).'%',
            ]))
            ->when($barangay, fn ($q) => $q->where('barangay_id', $barangay))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate(15)->withQueryString();

        return view('beneficiaries.index', [
            'beneficiaries' => $beneficiaries,
            'barangays' => Barangay::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
