<?php

use Bcl\Toolkit\Auth\PasswordLogin;

it('keeps deployed panels SSO-only by default', function () {
    config(['filament-socialite.password_login' => null]);

    expect(app()->isLocal())->toBeFalse()
        ->and(PasswordLogin::enabled())->toBeFalse();
});

it('opts a deployed panel into password login via config', function () {
    config(['filament-socialite.password_login' => true]);

    expect(PasswordLogin::enabled())->toBeTrue();
});

it('always allows password login in local development', function () {
    config(['filament-socialite.password_login' => false]);
    app()['env'] = 'local';

    expect(PasswordLogin::enabled())->toBeTrue();

    app()['env'] = 'testing';
});
