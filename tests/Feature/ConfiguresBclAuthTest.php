<?php

use Bcl\Toolkit\Auth\ConfiguresBclAuth;
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

it('registers the mcp:admin Passport scope', function () {
    expect(Passport::hasScope('mcp:admin'))->toBeTrue();
});

it('merges its scopes into app scopes without clobbering either', function () {
    // An app provider booting later replaces the list wholesale (baton,
    // fileclerk), sometimes re-describing mcp:use.
    Passport::tokensCan([
        'mcp:use' => 'App wording',
        'context:herd' => 'Act in herd',
    ]);

    ConfiguresBclAuth::registerScopes();

    expect(Passport::$scopes)->toBe([
        'mcp:use' => 'App wording',
        'mcp:admin' => 'Administer this app through MCP',
        'context:herd' => 'Act in herd',
    ]);
});

it('re-adds its scopes after a later provider replaces the list', function () {
    // ClobberingScopesProvider boots after the toolkit and calls
    // Passport::tokensCan(['context:herd' => ...]); the booted() merge
    // restores the toolkit's scopes.
    expect(array_keys(Passport::$scopes))->toBe(['mcp:use', 'mcp:admin', 'context:herd']);
});
