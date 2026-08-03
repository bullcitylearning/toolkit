<?php

use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Server\Registrar;

it('registers the shared mcp rate limiter', function () {
    expect(RateLimiter::limiter('mcp'))->not->toBeNull();
});

it('keys the mcp limiter on token id with an ip fallback', function () {
    $limiter = RateLimiter::limiter('mcp');

    $limit = $limiter(request());

    expect($limit->maxAttempts)->toBe(120)
        ->and($limit->key)->toContain('mcp:ip:');
});

it('registers the bclWeb route macro', function () {
    expect(Registrar::hasMacro('bclWeb'))->toBeTrue();
});
