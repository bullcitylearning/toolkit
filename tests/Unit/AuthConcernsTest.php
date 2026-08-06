<?php

use Bcl\Toolkit\Auth\Concerns\HasApiTokens;
use Bcl\Toolkit\Auth\Concerns\HasPanelDomainAllowlist;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as AuthUser;

class DualTokenUser extends AuthUser
{
    use HasApiTokens;
}

class AllowlistedUser extends AuthUser
{
    use HasPanelDomainAllowlist;

    protected $guarded = [];
}

it('speaks Sanctum for the shared token methods', function () {
    $sanctumFile = (new ReflectionClass(Laravel\Sanctum\HasApiTokens::class))->getFileName();
    $user = new ReflectionClass(DualTokenUser::class);

    foreach (['tokens', 'createToken', 'currentAccessToken', 'withAccessToken', 'tokenCan'] as $method) {
        expect($user->getMethod($method)->getFileName())->toBe($sanctumFile);
    }
});

it('keeps the Passport members the apps use reachable', function () {
    foreach (['oauthApps', 'createPassportToken', 'getProviderName'] as $method) {
        expect(method_exists(DualTokenUser::class, $method))->toBeTrue();
    }
});

it('lets everyone into the panel when no allowlist is configured', function () {
    config(['filament-socialite.domain_allowlist' => []]);

    $user = new AllowlistedUser(['email' => 'anyone@example.com']);

    expect($user->canAccessPanel(mock(Panel::class)))->toBeTrue();
});

it('gates panel access by email domain, case-insensitively and @-tolerant', function () {
    config(['filament-socialite.domain_allowlist' => ['@Allowed-Org.test']]);

    $staff = new AllowlistedUser(['email' => 'staff@allowed-org.test']);
    $outsider = new AllowlistedUser(['email' => 'intruder@gmail.com']);

    expect($staff->canAccessPanel(mock(Panel::class)))->toBeTrue()
        ->and($outsider->canAccessPanel(mock(Panel::class)))->toBeFalse();
});
