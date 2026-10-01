<?php

namespace Bcl\Toolkit\Mcp;

use Laravel\Mcp\Server\Registrar;

/**
 * laravel/mcp's OAuth discovery payloads, with the scopes a BCL resource
 * needs. Reuses the vendor's protected builders (hence the subclass) so
 * only scopes_supported differs from what oauthRoutes() serves.
 */
class OAuthMetadata extends Registrar
{
    public const ADMIN_SCOPE = 'mcp:admin';

    /**
     * Protected-resource metadata for /mcp/admin: a client discovering it
     * requests mcp:admin, the scope the admin route checks.
     *
     * @return array<string, mixed>
     */
    public static function adminResource(string $path): array
    {
        return [
            ...static::protectedResourceMetadata($path),
            'scopes_supported' => [self::ADMIN_SCOPE],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function authorizationServer(string $oauthPrefix = 'oauth'): array
    {
        return [
            ...static::authorizationServerMetadata($oauthPrefix),
            'scopes_supported' => [self::OAUTH_SCOPE, self::ADMIN_SCOPE],
        ];
    }
}
