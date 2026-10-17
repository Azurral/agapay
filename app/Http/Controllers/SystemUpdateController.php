<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Support\UpdateChecker;
use App\Support\UpdateLauncher;
use Illuminate\Http\JsonResponse;
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

    /**
     * Polled by the updating page: the version that answers now, and the updater's state (running while
     * data\updates\pending.json exists, then done or failed from result.json).
     */
    public function status(): JsonResponse
    {
        $updates = (string) ($_SERVER['AGAPAY_HOME'] ?? getenv('AGAPAY_HOME') ?: '').'/data/updates';
        $result = is_file($updates.'/result.json') ? json_decode((string) file_get_contents($updates.'/result.json'), true) : null;
        $state = match (true) {
            is_file($updates.'/pending.json') => 'running',
            ($result['status'] ?? null) === 'failed' => 'failed',
            ($result['status'] ?? null) === 'done' => 'done',
            default => 'unknown',
        };

        return response()->json(['version' => config('agapay.version'), 'state' => $state]
            + ($state === 'failed' ? ['message' => (string) ($result['message'] ?? '')] : []));
    }
}
