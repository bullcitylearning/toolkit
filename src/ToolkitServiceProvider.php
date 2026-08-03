<?php

namespace Bcl\Toolkit;

use Bcl\Toolkit\Auth\ConfiguresBclAuth;
use Bcl\Toolkit\Brand\Brand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;

class ToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/brands.php', 'brands');

        $this->registerBrandMailerDefaults();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bcl-toolkit');

        $this->publishes([
            __DIR__.'/../config/brands.php' => config_path('brands.php'),
        ], 'bcl-toolkit-config');

        $this->publishes([
            __DIR__.'/../resources/views/filament-socialite/buttons.blade.php' => resource_path('views/vendor/filament-socialite/components/buttons.blade.php'),
        ], 'bcl-toolkit-views');

        ConfiguresBclAuth::apply();

        $this->registerMcpRateLimiter();
        $this->registerMcpRouteMacro();
    }

    /**
     * Give every brand a named mailer so Brand::mailer() targets always
     * exist. The default mirrors the app's smtp mailer; apps needing
     * DMARC-aligned per-brand transports define mail.mailers.<brand> in
     * their own config/mail.php (pulse does).
     */
    protected function registerBrandMailerDefaults(): void
    {
        foreach (Brand::cases() as $brand) {
            if (config("mail.mailers.{$brand->value}") === null) {
                config(["mail.mailers.{$brand->value}" => config('mail.mailers.smtp')]);
            }
        }
    }

    /**
     * The shared throttle:mcp limiter: 120/min keyed on the access token id,
     * falling back to IP for unauthenticated requests. Apps may override by
     * re-registering "mcp" in their own provider (app providers boot later).
     */
    protected function registerMcpRateLimiter(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            $token = $request->user()?->currentAccessToken();
            $key = $token?->id ? 'mcp:token:'.$token->id : 'mcp:ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });
    }

    /**
     * Mcp::bclWeb(Server::class, patPath: '/mcp/tasks') registers the whole
     * BCL web MCP surface: the canonical Passport-guarded /mcp endpoint, an
     * optional Sanctum PAT endpoint, and the OAuth discovery routes.
     */
    protected function registerMcpRouteMacro(): void
    {
        Registrar::macro('bclWeb', function (string $server, ?string $patPath = null): void {
            /** @var Registrar $this */
            if ($patPath !== null) {
                $this->web($patPath, $server)->middleware(['auth:sanctum', 'throttle:mcp']);
            }

            $this->web('/mcp', $server)->middleware(['auth:api', 'throttle:mcp']);

            $this->oauthRoutes();
        });
    }
}
