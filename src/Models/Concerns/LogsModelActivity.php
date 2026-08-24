<?php

namespace Bcl\Toolkit\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * App-owned wrapper around spatie/laravel-activitylog.
 *
 * Schema requirements: the package's `activity_log` table must exist
 * (publish and run its migration:
 * `php artisan vendor:publish --tag=laravel-activitylog-migrations`).
 *
 * Models never `use` the vendor trait directly — override
 * $activityLogExcluded (or getActivitylogOptions()) here or per model
 * instead, so activity-log policy lives in exactly one file.
 */
trait LogsModelActivity
{
    use LogsActivity;

    /**
     * Columns never worth logging on any model: secrets, and large
     * JSON blobs whose old+new diff bloats each activity_log row.
     * (users.metadata old+new alone reached ~140KB/row during the
     * recursive-metadata-corruption window.)
     */
    protected array $activityLogExcluded = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'metadata',
        'privacy',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // logAll() seeds logAttributes=['*']; logExcept() then
            // removes the heavy/secret columns from that expansion.
            // (logExcept WITHOUT logAll is a no-op — there's no base
            // list to subtract from.)
            ->logAll()
            ->logExcept($this->activityLogExcluded)
            // Only record columns that actually changed, and skip the
            // write entirely when nothing meaningful did — kills the
            // timestamp-only / no-op log noise.
            ->logOnlyDirty()
            // v4's dontSubmitEmptyLogs(); renamed in activitylog v5,
            // same semantics (logEmptyChanges = false).
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }
}
