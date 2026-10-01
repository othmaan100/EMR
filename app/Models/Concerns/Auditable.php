<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Records created/updated/deleted events for a model in the audit log.
 * Hidden attributes (passwords, tokens) and timestamps are never recorded.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            Audit::log('created', static::auditLabel($model).' created', $model, null, $model->auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $new = $model->auditValues($model->getChanges());

            if (empty($new)) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $new);
            Audit::log('updated', static::auditLabel($model).' updated', $model, $model->auditValues($old), $new);
        });

        static::deleted(function (Model $model) {
            Audit::log('deleted', static::auditLabel($model).' deleted', $model, $model->auditValues($model->getAttributes()));
        });
    }

    protected static function auditLabel(Model $model): string
    {
        return class_basename($model).' #'.$model->getKey();
    }

    protected function auditValues(array $values): array
    {
        $excluded = array_merge($this->getHidden(), ['created_at', 'updated_at']);

        return array_diff_key($values, array_flip($excluded));
    }
}
