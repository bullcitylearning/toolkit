<?php

namespace Bcl\Toolkit\Mcp;

use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Passport\AccessToken as PassportAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The activity-log row for every GatedTool call: log "mcp", event
 * tool.called (outcome ok|error) or tool.refused (outcome forbidden),
 * caused by the user. Arguments are logged with secret-shaped keys
 * redacted; response bodies never are.
 */
class ToolAudit
{
    public const LOG_NAME = 'mcp';

    public const REDACTED = '[redacted]';

    public const SECRET_KEY_PATTERN = '/(pass|secret|token|key|credential|authorization|cookie)/i';

    /**
     * The server class currently being served, recorded by the provider's
     * resolving hook on Laravel\Mcp\Server.
     *
     * @var class-string|null
     */
    public static ?string $server = null;

    public static function record(GatedTool $tool, Request $request, string $outcome): void
    {
        $user = $request->user();

        activity(self::LOG_NAME)
            ->causedBy($user instanceof Model ? $user : null)
            ->event($outcome === 'forbidden' ? 'tool.refused' : 'tool.called')
            ->withProperties([
                'server' => self::$server,
                'tool' => $tool->name(),
                'capability' => $tool->requiredCapability(),
                ...self::credential($user),
                'outcome' => $outcome,
                'arguments' => self::redact($request->all()),
            ])
            ->log(sprintf('MCP tool %s %s', $tool->name(), $outcome === 'forbidden' ? 'refused' : 'called'));
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public static function redact(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key)) {
                $arguments[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $arguments[$key] = self::redact($value);
            }
        }

        return $arguments;
    }

    /**
     * @return array{auth: string|null, client_id: string|null, token_id: int|string|null}
     */
    private static function credential(mixed $user): array
    {
        $token = is_object($user) && method_exists($user, 'currentAccessToken')
            ? $user->currentAccessToken()
            : null;

        return match (true) {
            // toArray() reads the validated request attributes; the magic
            // getters would fall back to a database lookup.
            $token instanceof PassportAccessToken => [
                'auth' => 'passport',
                'client_id' => isset($token->toArray()['oauth_client_id']) ? (string) $token->toArray()['oauth_client_id'] : null,
                'token_id' => $token->toArray()['oauth_access_token_id'] ?? null,
            ],
            $token instanceof PersonalAccessToken => [
                'auth' => 'sanctum',
                'client_id' => null,
                'token_id' => $token->getKey(),
            ],
            $user !== null => ['auth' => 'session', 'client_id' => null, 'token_id' => null],
            default => ['auth' => null, 'client_id' => null, 'token_id' => null],
        };
    }
}
