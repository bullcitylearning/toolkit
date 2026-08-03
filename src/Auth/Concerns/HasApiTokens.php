<?php

namespace Bcl\Toolkit\Auth\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Passport\Passport;
use Laravel\Passport\PersonalAccessTokenFactory;
use Laravel\Passport\PersonalAccessTokenResult;
use Laravel\Sanctum\HasApiTokens as SanctumHasApiTokens;
use LogicException;

/**
 * Both token stacks on one User: Sanctum's trait handles self-issued
 * personal access tokens (createToken()/tokens()/currentAccessToken(),
 * with an untyped withAccessToken() that also accepts Passport's
 * AccessToken, so the auth:api guard works unchanged), plus the Passport
 * members the BCL apps exercise. The two vendor traits declare an
 * incompatible $accessToken property, so they cannot be composed directly.
 */
trait HasApiTokens
{
    use SanctumHasApiTokens;

    /**
     * Passport's HasApiTokens equivalent for OAuth client lookups.
     */
    public function oauthApps(): MorphMany
    {
        return $this->morphMany(Passport::clientModel(), 'owner');
    }

    /**
     * Issue a Passport personal access token (used by MCP token commands).
     *
     * @param  string[]  $scopes
     */
    public function createPassportToken(string $name, array $scopes = []): PersonalAccessTokenResult
    {
        return app(PersonalAccessTokenFactory::class)->make(
            $this->getAuthIdentifier(),
            $name,
            $scopes,
            $this->getProviderName(),
        );
    }

    /**
     * Passport's PassportUserProvider asks for this when resolving the user.
     */
    public function getProviderName(): string
    {
        $providers = collect(config('auth.guards'))->where('driver', 'passport')->pluck('provider')->all();

        foreach (config('auth.providers') as $provider => $config) {
            if (in_array($provider, $providers) && $config['driver'] === 'eloquent' && is_a($this, $config['model'])) {
                return $provider;
            }
        }

        throw new LogicException('Unable to determine authentication provider for this model from configuration.');
    }
}
