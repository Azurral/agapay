<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/** The only write path into audit_logs. */
final class AuditLogger
{
    /** Never persisted, whatever the caller passes. */
    private const SECRET_KEYS = ['password', 'remember_token'];

    private const TIMESTAMP_KEYS = ['created_at', 'updated_at'];

    public static function record(
        string $action,
        ?Model $subject = null,
        ?string $label = null,
        array $old = [],
        array $new = [],
        ?User $actor = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => $actor?->getKey() ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'record_label' => $label ?? ($subject && method_exists($subject, 'auditRecordLabel') ? $subject->auditRecordLabel() : null),
            'old_values' => self::clean($old),
            'new_values' => self::clean($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private static function clean(array $values): ?array
    {
        $clean = Arr::except($values, [...self::SECRET_KEYS, ...self::TIMESTAMP_KEYS]);

        return $clean === [] ? null : $clean;
    }
}
