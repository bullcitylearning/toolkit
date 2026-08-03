<?php

namespace Bcl\Toolkit\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Models with an optional expires_at column: null means "never expires".
 * Housekeeping commands stay app-specific (teardown differs per app) but
 * should query through the expired() scope.
 */
trait Expirable
{
    public function initializeExpirable(): void
    {
        $this->casts['expires_at'] = 'datetime';
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
