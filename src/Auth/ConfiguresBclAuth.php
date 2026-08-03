<?php

namespace Bcl\Toolkit\Auth;

use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use SocialiteProviders\Azure\AzureExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

/**
 * The shared BCL auth boot: M365 socialite providers, the MCP OAuth scope,
 * the packaged authorization view, and the toolkit PAT model. Runs from
 * ToolkitServiceProvider::boot(); app providers boot later, so an app can
 * override any piece (e.g. a custom PersonalAccessToken model).
 */
class ConfiguresBclAuth
{
    public static function apply(): void
    {
        Event::listen(SocialiteWasCalled::class, [AzureExtendSocialite::class, 'handle']);
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);

        Passport::tokensCan([
            'mcp:use' => 'Use MCP server tools and resources',
        ]);

        Passport::authorizationView('bcl-toolkit::passport.authorize');

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
