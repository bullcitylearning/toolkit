<?php

namespace Bcl\Toolkit\Mcp\Methods;

use Generator;
use Laravel\Mcp\Server\Methods\CallTool as BaseCallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * laravel/mcp resolves tools/call through the same shouldRegister() filter
 * as tools/list, so a hidden tool would never reach its handle(). The
 * toolkit binds this subclass in place of CallTool; it only marks which
 * tool is being called while the parent dispatches, letting GatedTool
 * refuse and audit the call itself. Ungated tools take the parent's path
 * unchanged.
 */
class CallTool extends BaseCallTool
{
    private static ?string $calling = null;

    public static function isCalling(string $tool): bool
    {
        return self::$calling === $tool;
    }

    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $previous = self::$calling;
        self::$calling = is_string($request->get('name')) ? $request->get('name') : null;

        try {
            return parent::handle($request, $context);
        } finally {
            self::$calling = $previous;
        }
    }
}
