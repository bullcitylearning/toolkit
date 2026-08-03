<?php

use Bcl\Toolkit\Auth\PersonalAccessToken;

it('parses scope slugs from prefixed abilities', function () {
    $token = new PersonalAccessToken;
    $token->abilities = ['org:bcl', 'org:bcb', 'mcp:use', 42];

    expect($token->scopeSlugs('org:')->all())->toBe(['bcl', 'bcb']);
});

it('returns an empty collection when no abilities match', function () {
    $token = new PersonalAccessToken;
    $token->abilities = ['mcp:use'];

    expect($token->scopeSlugs('context:')->isEmpty())->toBeTrue();
});

it('handles null abilities', function () {
    $token = new PersonalAccessToken;

    expect($token->scopeSlugs('org:')->isEmpty())->toBeTrue();
});
