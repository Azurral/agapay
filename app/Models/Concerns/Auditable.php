<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * Writes an audit row for every create / update / delete / restore.
 * Using models declare `protected string $auditSubject` and implement auditRecordLabel().
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::record('Added '.$model->auditSubject(), $model, null, [], $model->attributesToArray());
        });

        static::updated(function (Model $model) {
            $changes = Arr::except($model->getChanges(), ['updated_at', ...$model->auditIgnoredAttributes()]);

            if ($changes === []) {
                return;
            }

            AuditLogger::record(
                'Updated '.$model->auditSubject(),
                $model,
                null,
                Arr::only($model->getOriginal(), array_keys($changes)),
                $changes,
            );
        });

        static::deleted(function (Model $model) {
            $softDeleted = in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting();

            AuditLogger::record(($softDeleted ? 'Archived ' : 'Deleted ').$model->auditSubject(), $model);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn (Model $model) => AuditLogger::record('Restored '.$model->auditSubject(), $model));
        }
    }

    public function auditSubject(): string
    {
        return $this->auditSubject ?? class_basename($this);
    }

    /** @return list<string> */
    public function auditIgnoredAttributes(): array
    {
        return $this->auditIgnore ?? [];
    }

    abstract public function auditRecordLabel(): string;
}
