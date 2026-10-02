<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Data Encoders see their encoding queue; Admin and Agri Tech see all beneficiaries (Figma).
        $showsEncodingQueue = $request->user()->role?->slug === Role::ENCODER;

        return view('dashboard', [
            'showsEncodingQueue' => $showsEncodingQueue,
            'queue' => $showsEncodingQueue
                ? Beneficiary::whereNotNull('encoding_issue')->oldest()->oldest('id')->limit(10)->get()
                : collect(),
            'recent' => $showsEncodingQueue
                ? collect()
                : Beneficiary::forTable()->latest('updated_at')->latest('id')->limit(10)->get(),
        ]);
    }
}
