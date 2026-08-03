<?php

use Bcl\Toolkit\Support\Ttl;
use Illuminate\Support\Carbon;

it('parses hour, day, and week ttls into absolute expiries', function () {
    Carbon::setTestNow('2026-08-03 12:00:00');

    expect(Ttl::parse('12h')->toDateTimeString())->toBe('2026-08-04 00:00:00')
        ->and(Ttl::parse('7d')->toDateTimeString())->toBe('2026-08-10 12:00:00')
        ->and(Ttl::parse('2w')->toDateTimeString())->toBe('2026-08-17 12:00:00');

    Carbon::setTestNow();
});

it('parses never as null', function () {
    expect(Ttl::parse('never'))->toBeNull()
        ->and(Ttl::parse(' NEVER '))->toBeNull();
});

it('rejects malformed ttls', function () {
    Ttl::parse('7 days');
})->throws(InvalidArgumentException::class, 'Invalid ttl');

it('validates without throwing', function () {
    expect(Ttl::isValid('30d'))->toBeTrue()
        ->and(Ttl::isValid('soon'))->toBeFalse();
});
