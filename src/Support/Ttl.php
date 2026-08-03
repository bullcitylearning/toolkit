<?php

namespace Bcl\Toolkit\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

class Ttl
{
    /**
     * Parse a TTL string ("7d", "12h", "2w", "30d", "never") into an absolute
     * expiry, or null for "never".
     */
    public static function parse(string $ttl): ?Carbon
    {
        $ttl = strtolower(trim($ttl));

        if ($ttl === 'never') {
            return null;
        }

        if (! preg_match('/^(\d+)([hdw])$/', $ttl, $m)) {
            throw new InvalidArgumentException(
                "Invalid ttl [{$ttl}] — use a number plus h/d/w (e.g. 12h, 7d, 2w) or \"never\"."
            );
        }

        $amount = (int) $m[1];

        return match ($m[2]) {
            'h' => now()->addHours($amount),
            'd' => now()->addDays($amount),
            'w' => now()->addWeeks($amount),
        };
    }

    public static function isValid(string $ttl): bool
    {
        try {
            self::parse($ttl);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
