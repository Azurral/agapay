<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            // Data Encoders see their encoding queue; Admin and Agri Tech see all beneficiaries (Figma).
            'showsEncodingQueue' => $request->user()->role?->slug === Role::ENCODER,
        ]);
    }
}
