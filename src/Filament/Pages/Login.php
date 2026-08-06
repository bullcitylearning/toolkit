<?php

namespace Bcl\Toolkit\Filament\Pages;

use Bcl\Toolkit\Auth\PasswordLogin;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    /**
     * The panel is SSO-only unless password login is enabled (always in
     * local development, opt-in elsewhere via the
     * filament-socialite.password_login config key — see PasswordLogin).
     * When SSO-only, the page shows just the Microsoft button (rendered by
     * filament-socialite through the AUTH_LOGIN_FORM_AFTER hook).
     */
    public function content(Schema $schema): Schema
    {
        if (PasswordLogin::enabled()) {
            return parent::content($schema);
        }

        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
            ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return PasswordLogin::enabled()
            ? parent::getSubheading()
            : __('Sign in with your BCL Microsoft 365 account.');
    }
}
