<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class AuditTrailController extends Controller
{
    public function index(Request $request): View
    {
        $timestamp = $request->queryText('timestamp');
        $user = $request->queryText('user');
        $action = $request->queryText('action');
        $date = $this->parseDate($timestamp);
        $invalidDate = $timestamp !== '' && $date === null;

        $logs = AuditLog::query()
            ->with('user:id,username')
            ->when($user !== '', fn ($query) => $query->whereHas(
                'user', fn ($u) => $u->where('username', 'like', '%'.$user.'%')
            ))
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($date, fn ($query) => $query->whereDate('created_at', $date))
            ->when($invalidDate, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all(),
            'invalidDate' => $invalidDate,
        ]);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
