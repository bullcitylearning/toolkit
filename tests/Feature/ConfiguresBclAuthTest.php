<?php

use Bcl\Toolkit\Auth\PersonalAccessToken;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use SocialiteProviders\Manager\SocialiteWasCalled;

it('listens for both Microsoft socialite providers', function () {
    expect(Event::hasListeners(SocialiteWasCalled::class))->toBeTrue();
});

it('registers the mcp:use Passport scope', function () {
    expect(Passport::hasScope('mcp:use'))->toBeTrue();
});

it('serves the packaged authorization view', function () {
    expect(view()->exists('bcl-toolkit::passport.authorize'))->toBeTrue();
});

it('defaults Sanctum to the toolkit PAT model', function () {
    expect(Sanctum::$personalAccessTokenModel)->toBe(PersonalAccessToken::class);
});
