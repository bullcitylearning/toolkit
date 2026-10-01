<?php

namespace Bcl\Toolkit\Auth;

use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use SocialiteProviders\Azure\AzureExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

/**
 * The shared BCL auth boot: M365 socialite providers, the MCP OAuth scopes,
 * the packaged authorization view, and the toolkit PAT model. Runs from
 * ToolkitServiceProvider::boot(); app providers boot later, so an app can
 * override any piece (e.g. a custom PersonalAccessToken model).
 */
class ConfiguresBclAuth
{
    /**
     * mcp:use for /mcp; mcp:admin for the optional /mcp/admin server.
     */
    public const SCOPES = [
        'mcp:use' => 'Use MCP server tools and resources',
        'mcp:admin' => 'Administer this app through MCP',
    ];

    public static function apply(): void
    {
        Event::listen(SocialiteWasCalled::class, [AzureExtendSocialite::class, 'handle']);
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);

        static::registerScopes();
        app()->booted(static::registerScopes(...));

        Passport::authorizationView('bcl-toolkit::passport.authorize');

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * Merge the toolkit's MCP scopes into whatever the app has registered,
     * never overwriting an app's own description. Runs at boot and again
     * once the app has booted, because Passport::tokensCan() replaces the
     * whole list and app providers (baton, fileclerk) call it later.
     */
    public static function registerScopes(): void
    {
        Passport::tokensCan([
            ...self::SCOPES,
            ...Passport::$scopes,
        ]);
    }
}
