<?php

namespace Bcl\Toolkit;

use Bcl\Toolkit\Auth\ConfiguresBclAuth;
use Bcl\Toolkit\Mcp\Methods\CallTool;
use Bcl\Toolkit\Mcp\OAuthMetadata;
use Bcl\Toolkit\Mcp\ToolAudit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Methods\CallTool as BaseCallTool;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Http\Middleware\CheckToken;

class ToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/brands.php', 'brands');

        $this->registerBrandMailerDefaults();
        $this->registerGatedToolDispatch();
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

        $this->registerMcpRateLimiters();
        $this->registerMcpRouteMacro();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\McpTokenCommand::class,
            ]);
        }
    }

    /**
     * Give every registered brand a named mailer so Brand::mailer() targets
     * always exist. The default mirrors the app's smtp mailer; apps needing
     * DMARC-aligned per-brand transports define mail.mailers.<brand> in
     * their own config/mail.php (pulse does). No-ops on an empty registry.
     */
    protected function registerBrandMailerDefaults(): void
    {
        foreach (array_keys(config('brands.registry', [])) as $slug) {
            if (config("mail.mailers.{$slug}") === null) {
                config(["mail.mailers.{$slug}" => config('mail.mailers.smtp')]);
            }
        }
    }

    /**
     * GatedTool support: tools/call dispatches through the toolkit's
     * CallTool (so a hidden gated tool is refused and audited rather than
     * "not found"), and the served server class is recorded for audit rows.
     */
    protected function registerGatedToolDispatch(): void
    {
        $this->app->bind(BaseCallTool::class, CallTool::class);

        $this->app->resolving(Server::class, function (Server $server): void {
            ToolAudit::$server = $server::class;
        });
    }

    /**
     * The shared limiters, keyed on the access token id and falling back
     * to IP for unauthenticated requests: throttle:mcp at 120/min and
     * throttle:mcp-admin at 30/min. Apps may override either by
     * re-registering it in their own provider (app providers boot later).
     */
    protected function registerMcpRateLimiters(): void
    {
        foreach (['mcp' => 120, 'mcp-admin' => 30] as $name => $perMinute) {
            RateLimiter::for($name, function (Request $request) use ($name, $perMinute) {
                $token = $request->user()?->currentAccessToken();
                $key = $token?->id ? "{$name}:token:".$token->id : "{$name}:ip:".$request->ip();

                return Limit::perMinute($perMinute)->by($key);
            });
        }
    }

    /**
     * Mcp::bclWeb(Server::class, patPath: '/mcp/tasks', admin: AdminServer::class)
     * registers the whole BCL web MCP surface: the canonical Passport-guarded
     * /mcp endpoint, an optional Sanctum PAT endpoint, an optional /mcp/admin
     * server that requires the mcp:admin scope, and the OAuth discovery
     * routes (advertising mcp:admin for the admin resource).
     */
    protected function registerMcpRouteMacro(): void
    {
        Registrar::macro('bclWeb', function (string $server, ?string $patPath = null, ?string $admin = null): void {
            /** @var Registrar $this */
            if ($patPath !== null) {
                $this->web($patPath, $server)->middleware(['auth:sanctum', 'throttle:mcp']);
            }

            $this->web('/mcp', $server)->middleware(['auth:api', 'throttle:mcp']);

            if ($admin !== null) {
                $this->web('/mcp/admin', $admin)->middleware([
                    'auth:api',
                    CheckToken::using(OAuthMetadata::ADMIN_SCOPE),
                    'throttle:mcp-admin',
                ]);

                // Registered before oauthRoutes(): its exact authorization-server
                // route then stands aside, and this exact protected-resource route
                // is matched ahead of the nested wildcard.
                Route::get('/.well-known/oauth-protected-resource/mcp/admin', fn () => response()->json(OAuthMetadata::adminResource('mcp/admin')))
                    ->name('bcl.mcp.oauth.protected-resource.admin');
                Route::get('/.well-known/oauth-authorization-server', fn () => response()->json(OAuthMetadata::authorizationServer()))
                    ->name('mcp.oauth.authorization-server');
            }

            $this->oauthRoutes();
        });
    }
}
