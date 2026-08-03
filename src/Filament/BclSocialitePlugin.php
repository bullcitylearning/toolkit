<?php

namespace Bcl\Toolkit\Filament;

use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

/**
 * The shared M365 SSO plugin block every BCL admin panel uses. Returns the
 * plugin, so an app can chain further customization before handing it to
 * ->plugin().
 */
class BclSocialitePlugin
{
    public static function make(): FilamentSocialitePlugin
    {
        return FilamentSocialitePlugin::make()
            ->providers([
                Provider::make('azure')
                    ->label('Microsoft')
                    ->icon('heroicon-o-building-office-2'),
            ])
            ->registration(true)
            ->domainAllowList(config('filament-socialite.domain_allowlist', []))
            // No password form above the button in production, so the
            // "or login via" divider would dangle.
            ->showDivider(app()->isLocal())
            ->createUserUsing(function (string $provider, SocialiteUserContract $oauthUser) {
                $model = config('auth.providers.users.model');

                return $model::create([
                    'name' => $oauthUser->getName() ?? $oauthUser->getEmail(),
                    'email' => $oauthUser->getEmail(),
                    'password' => bcrypt(Str::random(32)),
                ]);
            });
    }
}
