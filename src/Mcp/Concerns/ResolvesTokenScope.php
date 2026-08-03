<?php

namespace Bcl\Toolkit\Mcp\Concerns;

use Bcl\Toolkit\Auth\PersonalAccessToken;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Passport\AccessToken as PassportAccessToken;
use Laravel\Passport\Contracts\ScopeAuthorizable;

/**
 * Shared core for resolving what a token may act on. Sanctum PATs carry
 * explicit "<prefix><slug>" abilities (publish: "org:bcl", baton:
 * "context:herd"); Passport (OAuth) tokens belong to a first-party user
 * and get app-defined fallback scope.
 */
trait ResolvesTokenScope
{
    /**
     * Ability prefix marking scope grants on Sanctum PATs. Override per app
     * (e.g. "org:", "context:").
     */
    protected function scopeAbilityPrefix(): string
    {
        return 'scope:';
    }

    protected function sanctumToken(Request $request): ?PersonalAccessToken
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'currentAccessToken')) {
            return null;
        }

        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    /**
     * Slugs the current Sanctum token grants via the app's ability prefix.
     * Empty for Passport-authenticated requests — apps decide that fallback.
     *
     * @return Collection<int, string>
     */
    protected function tokenScopeSlugs(Request $request): Collection
    {
        return $this->sanctumToken($request)?->scopeSlugs($this->scopeAbilityPrefix()) ?? collect();
    }

    protected function isPassportAuthenticated(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'currentAccessToken')) {
            return false;
        }

        $token = $user->currentAccessToken();

        // Bearer-token flow attaches Laravel\Passport\AccessToken; cookie /
        // first-party flow attaches a TransientToken. Both implement
        // ScopeAuthorizable, which Sanctum's PersonalAccessToken does not.
        return $token instanceof PassportAccessToken
            || $token instanceof ScopeAuthorizable;
    }
}
