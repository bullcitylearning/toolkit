<?php

use Bcl\Toolkit\Auth\Concerns\HasApiTokens;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;

class CommandTestUser extends AuthUser
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('personal_access_tokens', function ($table) {
        $table->id();
        $table->morphs('tokenable');
        $table->text('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    config(['auth.providers.users.model' => CommandTestUser::class]);

    CommandTestUser::create(['name' => 'Jim', 'email' => 'admin@example-org.test', 'password' => 'secret']);
});

it('issues a sanctum token with abilities and prints the config snippet', function () {
    $this->artisan('bcl:mcp-token', [
        'name' => 'claude-cowork-jim',
        '--driver' => 'sanctum',
        '--ability' => ['org:bcl'],
        '--path' => '/mcp/publish',
    ])
        ->expectsOutputToContain('MCP token (sanctum) issued for admin@example-org.test scoped to: org:bcl')
        ->expectsOutputToContain('/mcp/publish')
        ->assertSuccessful();

    $token = CommandTestUser::first()->tokens()->first();

    expect($token->name)->toBe('claude-cowork-jim')
        ->and($token->abilities)->toBe(['org:bcl']);
});

it('rejects unknown drivers', function () {
    $this->artisan('bcl:mcp-token', ['--driver' => 'jwt'])
        ->expectsOutputToContain('Unknown driver [jwt]')
        ->assertFailed();
});

it('fails plainly when no user exists', function () {
    CommandTestUser::query()->delete();

    $this->artisan('bcl:mcp-token', ['--driver' => 'sanctum'])
        ->expectsOutputToContain('No user found to own the token.')
        ->assertFailed();
});
