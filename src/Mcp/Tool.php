<?php

namespace Bcl\Toolkit\Mcp;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool as BaseTool;
use Throwable;

abstract class Tool extends BaseTool
{
    /**
     * Wrap a successful payload in the standard JSON envelope, giving
     * preparePayload() a chance to append cross-cutting notices first.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function respond(Request $request, array $payload): Response
    {
        return Response::json($this->preparePayload($request, $payload));
    }

    /**
     * Hook point for cross-cutting payload additions (e.g. the
     * instructions-version kit).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(Request $request, array $payload): array
    {
        return $payload;
    }

    protected function parseDateTime(?string $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            throw new InvalidArgumentException(
                "{$field} must be an ISO-8601 datetime, e.g. 2026-08-15T09:00:00-04:00."
            );
        }
    }
}
