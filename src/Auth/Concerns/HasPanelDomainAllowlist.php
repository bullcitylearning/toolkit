<?php

namespace Bcl\Toolkit\Auth\Concerns;

use Filament\Panel;
use Illuminate\Support\Str;

trait HasPanelDomainAllowlist
{
    /**
     * Filament denies everyone in non-local environments unless this is
     * implemented. Access mirrors the SSO domain allow-list: when it's
     * configured, only users with an allow-listed email domain get in;
     * with no allow-list, any authenticated user does.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        $allowlist = config('filament-socialite.domain_allowlist', []);

        if ($allowlist === []) {
            return true;
        }

        $domain = Str::lower(Str::afterLast((string) $this->email, '@'));

        return in_array($domain, array_map(fn (string $d) => Str::lower(ltrim($d, '@')), $allowlist), true);
    }
}
