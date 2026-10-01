<?php

namespace Bcl\Toolkit\Mcp;

use Bcl\Toolkit\Mcp\Methods\CallTool;
use Illuminate\Container\Container;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use LogicException;
use Throwable;

/**
 * A tool gated by one named capability — an ordinary Laravel ability, so
 * can() resolves through the app's gates, policies and spatie/permission.
 * The tool is hidden from tools/list for callers without it, re-checked on
 * every call, and every call (refusals included) is audited.
 *
 * Authors declare capability() and write perform(); both entry points are
 * final, so the re-check can't be forgotten or bypassed. perform() is
 * called through the container, so it may inject services after $request.
 *
 * The standard: jims_dev_way/playbooks/new-laravel-app.md step 5.
 */
abstract class GatedTool extends Tool
{
    /**
     * The ability this tool requires, e.g. "sends.approve". An ability the
     * app never defines is denied.
     */
    abstract protected function capability(): string;

    /**
     * Hidden unless the caller holds the capability. During a tools/call
     * for this very tool it stays resolvable, so the call reaches handle()
     * and is refused there with the standard envelope and an audit row,
     * instead of laravel/mcp's "not found".
     */
    final public function shouldRegister(Request $request): bool
    {
        if (CallTool::isCalling($this->name())) {
            return true;
        }

        return $this->allows($request);
    }

    final public function handle(Request $request): mixed
    {
        if (! method_exists($this, 'perform')) {
            throw new LogicException(static::class.' must define perform(Request $request).');
        }

        if (! $this->allows($request)) {
            ToolAudit::record($this, $request, 'forbidden');

            return Response::error(json_encode(
                ['error' => 'forbidden', 'capability' => $this->capability()],
                JSON_UNESCAPED_SLASHES,
            ));
        }

        try {
            $response = Container::getInstance()->call([$this, 'perform']);
        } catch (Throwable $e) {
            ToolAudit::record($this, $request, 'error');

            throw $e;
        }

        ToolAudit::record($this, $request, $this->isErrorResponse($response) ? 'error' : 'ok');

        return $response;
    }

    /**
     * The declared capability, for audit rows and test helpers.
     */
    final public function requiredCapability(): string
    {
        return $this->capability();
    }

    private function allows(Request $request): bool
    {
        return $request->user()?->can($this->capability()) ?? false;
    }

    private function isErrorResponse(mixed $response): bool
    {
        return match (true) {
            $response instanceof Response => $response->isError(),
            $response instanceof ResponseFactory => $response->responses()->contains(fn (Response $r): bool => $r->isError()),
            default => false,
        };
    }
}
