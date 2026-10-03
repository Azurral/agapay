<?php

namespace App\Support;

use App\Http\Requests\DamageReportRequest;
use Illuminate\Support\Facades\Log;

/** Raises PHP's memory limit for a heavy request (PDF rendering) without ever lowering a higher php.ini value. */
final class MemoryLimit
{
    public static function atLeast(string $limit): void
    {
        $current = (string) ini_get('memory_limit');
        if ($current === '-1' || DamageReportRequest::bytes($current) >= DamageReportRequest::bytes($limit)) {
            return;
        }

        if (ini_set('memory_limit', $limit) === false) {
            Log::warning("Could not raise memory_limit from {$current} to {$limit}; large PDFs may fail.");
        }
    }
}
