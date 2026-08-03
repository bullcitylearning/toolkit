<?php

namespace Bcl\Toolkit;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;

class ToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerMcpRateLimiter();
        $this->registerMcpRouteMacro();
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
