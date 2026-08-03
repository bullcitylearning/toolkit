<?php

namespace Bcl\Toolkit\Auth;

use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Slugs granted by abilities of the form "<prefix><slug>", e.g. "org:bcl"
     * or "context:herd".
     *
     * @return Collection<int, string>
     */
    public function scopeSlugs(string $prefix): Collection
    {
        return collect($this->abilities ?? [])
            ->filter(fn ($ability) => is_string($ability) && str_starts_with($ability, $prefix))
            ->map(fn ($ability) => substr($ability, strlen($prefix)))
            ->values();
    }
}
