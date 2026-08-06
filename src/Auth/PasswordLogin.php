<?php

namespace Bcl\Toolkit\Auth;

/**
 * Whether the panel login page accepts email/password credentials.
 *
 * Local development always does, so make:filament-user accounts work
 * without Azure credentials. Everywhere else it is opt-in through the
 * app-owned `filament-socialite.password_login` config key — for apps
 * whose users are not all on the org's Microsoft 365 tenant. The
 * default keeps BCL panels SSO-only in deployed environments.
 */
class PasswordLogin
{
    public static function enabled(): bool
    {
        return app()->isLocal() || (bool) config('filament-socialite.password_login', false);
    }
}
