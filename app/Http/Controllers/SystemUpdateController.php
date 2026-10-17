<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Support\UpdateChecker;
use App\Support\UpdateLauncher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The Administrator's "Update" button: starts the office copy's updater, then waits for AGAPAY to come back. */
class SystemUpdateController extends Controller
{
    public function store(Request $request, UpdateChecker $checker, UpdateLauncher $launcher): View|RedirectResponse
    {
        $release = $checker->available();
        if (! $release) {
            return back()->with('status', 'AGAPAY is already up to date.');
        }

        AuditLogger::record('Started Update', null, "AGAPAY v{$release['version']}", ['version' => config('agapay.version')], ['version' => $release['version']]);
        $launcher->launch($release['version'], $release['url']);

        return view('system.updating', ['version' => $release['version']]);
    }
}
