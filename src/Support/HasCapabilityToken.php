<?php

namespace Bcl\Toolkit\Support;

/**
 * Capability-URL tokens: possession of the string IS the permission
 * (pulse's respondent links, expanse's board share links). Generation is
 * cryptographically random hex; comparison is constant-time.
 */
trait HasCapabilityToken
{
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(static::capabilityTokenBytes()));
    }

    /**
     * Bytes of entropy before hex encoding (the token string is twice as
     * long). Override per model.
     */
    protected static function capabilityTokenBytes(): int
    {
        return 32;
    }

    protected static function tokenMatches(?string $stored, ?string $candidate): bool
    {
        return $stored !== null
            && $candidate !== null
            && $candidate !== ''
            && hash_equals($stored, $candidate);
    }
}
