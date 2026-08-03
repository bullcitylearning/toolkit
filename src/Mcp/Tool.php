<?php

namespace Bcl\Toolkit\Mcp;

use Bcl\Toolkit\Mcp\Contracts\HasInstructionsVersion;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool as BaseTool;
use Throwable;

abstract class Tool extends BaseTool
{
    /**
     * Schema description for the auto-added instructions_version parameter.
     */
    public const INSTRUCTIONS_VERSION_PARAM_DESCRIPTION = 'Echo the instructions_version stated in the server instructions on every call; the server flags stale guidance in results.';

    /**
     * The MCP server this tool is registered on, when that server maintains
     * an instructions-version contract. Override in the app's base tool.
     *
     * @return class-string<HasInstructionsVersion>|null
     */
    protected function instructionsVersionServer(): ?string
    {
        return null;
    }

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
     * Append the stale-guidance correction when the client echoed a stale
     * or missing instructions version. Tool results reach the model fresh
     * on every call, so this self-heals mid-session. Overriding tools
     * should call parent to keep the behavior.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(Request $request, array $payload): array
    {
        $server = $this->versionedServer();

        if ($server !== null) {
            $sent = $request->get('instructions_version');

            if ($sent === null || (int) $sent !== $server::instructionsVersion()) {
                $payload['instructions_notice'] = $server::staleInstructionsNotice($sent);
            }
        }

        return $payload;
    }

    /**
     * Advertise the instructions_version parameter on every tool of a
     * versioned server, without each schema() having to declare it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = parent::toArray();

        if ($this->versionedServer() !== null) {
            $properties = (array) ($result['inputSchema']['properties'] ?? []);

            $properties['instructions_version'] = [
                'type' => 'integer',
                'description' => self::INSTRUCTIONS_VERSION_PARAM_DESCRIPTION,
            ];

            $result['inputSchema']['properties'] = $properties;
        }

        return $result;
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

    /**
     * @return class-string<HasInstructionsVersion>|null
     */
    private function versionedServer(): ?string
    {
        $server = $this->instructionsVersionServer();

        return $server !== null && is_a($server, HasInstructionsVersion::class, true)
            ? $server
            : null;
    }
}
