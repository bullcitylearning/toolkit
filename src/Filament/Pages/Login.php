<?php

namespace Bcl\Toolkit\Filament\Pages;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    /**
     * The panel is SSO-only outside local development: the login page shows
     * just the Microsoft button (rendered by filament-socialite through the
     * AUTH_LOGIN_FORM_AFTER hook). Locally the email/password form stays so
     * make:filament-user accounts work without Azure credentials.
     */
    public function content(Schema $schema): Schema
    {
        if (app()->isLocal()) {
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
        return app()->isLocal() ? parent::getSubheading() : __('Sign in with your BCL Microsoft 365 account.');
    }
}
