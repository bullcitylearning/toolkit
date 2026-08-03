<?php

use Bcl\Toolkit\Support\Expirable;
use Bcl\Toolkit\Support\HasCapabilityToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ExpirableThing extends Model
{
    use Expirable;

    protected $table = 'expirable_things';

    protected $guarded = [];

    public $timestamps = false;
}

class TokenedThing extends Model
{
    use HasCapabilityToken;
}

class ShortTokenedThing extends Model
{
    use HasCapabilityToken;

    protected static function capabilityTokenBytes(): int
    {
        return 16;
    }
}

beforeEach(function () {
    Schema::create('expirable_things', function ($table) {
        $table->id();
        $table->timestamp('expires_at')->nullable();
    });
});

it('casts expires_at and reports expiry', function () {
    $expired = ExpirableThing::create(['expires_at' => now()->subMinute()]);
    $live = ExpirableThing::create(['expires_at' => now()->addDay()]);
    $forever = ExpirableThing::create(['expires_at' => null]);

    expect($expired->expires_at)->toBeInstanceOf(Illuminate\Support\Carbon::class)
        ->and($expired->isExpired())->toBeTrue()
        ->and($live->isExpired())->toBeFalse()
        ->and($forever->isExpired())->toBeFalse();
});

it('scopes to expired rows only, never-expiring rows excluded', function () {
    ExpirableThing::create(['expires_at' => now()->subMinute()]);
    ExpirableThing::create(['expires_at' => now()->addDay()]);
    ExpirableThing::create(['expires_at' => null]);

    expect(ExpirableThing::expired()->count())->toBe(1);
});

it('generates hex capability tokens at the configured length', function () {
    expect(TokenedThing::generateToken())->toHaveLength(64)->toMatch('/^[0-9a-f]+$/')
        ->and(ShortTokenedThing::generateToken())->toHaveLength(32);
});

it('compares capability tokens in constant time semantics', function () {
    $token = TokenedThing::generateToken();

    $matches = new ReflectionMethod(TokenedThing::class, 'tokenMatches');

    expect($matches->invoke(null, $token, $token))->toBeTrue()
        ->and($matches->invoke(null, $token, 'wrong'))->toBeFalse()
        ->and($matches->invoke(null, $token, null))->toBeFalse()
        ->and($matches->invoke(null, $token, ''))->toBeFalse()
        ->and($matches->invoke(null, null, $token))->toBeFalse();
});
