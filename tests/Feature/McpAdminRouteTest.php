<?php

use Bcl\Toolkit\Tests\Fixtures\Gated\AdminFixtureServer;
use Bcl\Toolkit\Tests\Fixtures\Gated\AppFixtureServer;
use Bcl\Toolkit\Tests\Fixtures\Gated\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Middleware\ReorderJsonAccept;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Passport;
use Spatie\Activitylog\Models\Activity;

/**
 * End to end through HTTP: real Passport tokens minted by bcl:mcp-token,
 * the auth:api guard and the mcp:admin scope check on /mcp/admin.
 */
beforeEach(function () {
    (require __DIR__.'/../../vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub')->up();

    foreach (glob(__DIR__.'/../../vendor/laravel/passport/database/migrations/*.php') as $migration) {
        (require $migration)->up();
    }

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $private);

    config([
        'auth.providers.users.model' => User::class,
        'auth.guards.api' => ['driver' => 'passport', 'provider' => 'users'],
        'passport.private_key' => $private,
        'passport.public_key' => openssl_pkey_get_details($key)['key'],
    ]);

    app(ClientRepository::class)->createPersonalAccessGrantClient('pat', 'users');

    Gate::define('widgets.purge', fn (): bool => true);
    Gate::define('widgets.approve', fn (): bool => true);

    $this->user = User::create(['name' => 'Jim', 'email' => 'admin@example-org.test', 'password' => 'x']);
});

function mintToken(string ...$scopes): string
{
    return User::first()->createPassportToken('probe', $scopes)->accessToken;
}

function rpc(string $method, array $params = []): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
}

function toolNames($response): array
{
    return collect($response->json('result.tools'))->pluck('name')->all();
}

describe('with admin:', function () {
    beforeEach(function () {
        Mcp::bclWeb(AppFixtureServer::class, admin: AdminFixtureServer::class);
        Route::getRoutes()->refreshNameLookups();
    });

    it('registers mcp:admin so bcl:mcp-token can mint an admin PAT', function () {
        expect(Passport::hasScope('mcp:admin'))->toBeTrue();

        $this->artisan('bcl:mcp-token', ['--scope' => ['mcp:admin'], '--path' => '/mcp/admin'])
            ->expectsOutputToContain('/mcp/admin')
            ->assertSuccessful();

        expect(Passport::token()->sole()->scopes)->toBe(['mcp:admin']);
    });

    it('refuses /mcp/admin to a token without mcp:admin', function () {
        $this->withToken(mintToken('mcp:use'))
            ->postJson('/mcp/admin', rpc('tools/list'))
            ->assertForbidden();
    });

    it('accepts /mcp/admin for a token with mcp:admin', function () {
        $response = $this->withToken(mintToken('mcp:admin'))
            ->postJson('/mcp/admin', rpc('tools/list'))
            ->assertOk();

        expect(toolNames($response))->toBe(['purge_everything']);
    });

    it('challenges an anonymous /mcp/admin request toward the admin metadata', function () {
        $this->postJson('/mcp/admin', rpc('tools/list'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp/admin').'"');
    });

    it('advertises mcp:admin for the admin resource only', function () {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/admin')
            ->assertOk()
            ->assertJson(['resource' => url('/mcp/admin'), 'scopes_supported' => ['mcp:admin']]);

        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertJson(['resource' => url('/mcp'), 'scopes_supported' => ['mcp:use']]);

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertJson(['scopes_supported' => ['mcp:use', 'mcp:admin']]);
    });

    it('never lists admin-server tools on /mcp, whatever the token carries', function () {
        $response = $this->withToken(mintToken('mcp:use', 'mcp:admin'))
            ->postJson('/mcp', rpc('tools/list'))
            ->assertOk();

        expect(toolNames($response))->not->toContain('purge_everything')
            ->and(toolNames($response))->toContain('approve_widget');

        $this->withToken(mintToken('mcp:use', 'mcp:admin'))
            ->postJson('/mcp', rpc('tools/call', ['name' => 'purge_everything']))
            ->assertJsonPath('error.message', 'Tool [purge_everything] not found.');
    });

    it('audits an admin call with the passport token', function () {
        $this->withToken(mintToken('mcp:admin'))
            ->postJson('/mcp/admin', rpc('tools/call', ['name' => 'purge_everything', 'arguments' => []]))
            ->assertOk()
            ->assertJsonPath('result.isError', false);

        $activity = Activity::query()->sole();

        expect($activity->properties->only(['server', 'tool', 'auth', 'outcome'])->all())->toBe([
            'server' => AdminFixtureServer::class,
            'tool' => 'purge_everything',
            'auth' => 'passport',
            'outcome' => 'ok',
        ])->and($activity->properties->get('client_id'))->not->toBeNull();
    });

    it('throttles /mcp/admin with its own limiter', function () {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'mcp/admin' && in_array('POST', $r->methods()));

        expect($route->middleware())->toContain('auth:api', 'throttle:mcp-admin')
            ->and($route->middleware())->toContain(CheckToken::class.':mcp:admin');
    });
});

it('registers exactly the pre-1.3 routes without admin:', function () {
    $before = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri())->all();

    Mcp::bclWeb(AppFixtureServer::class, patPath: '/mcp/pat');

    $added = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($r) => [implode('|', $r->methods()).' '.$r->uri(), $r->middleware(), $r->getName()])
        ->reject(fn ($r) => in_array($r[0], $before, true))
        ->values()
        ->all();

    expect($added)->toBe([
        ['GET|HEAD mcp/pat', [], null],
        ['DELETE mcp/pat', [], null],
        ['POST mcp/pat', [ReorderJsonAccept::class, AddWwwAuthenticateHeader::class, 'auth:sanctum', 'throttle:mcp'], null],
        ['GET|HEAD mcp', [], null],
        ['DELETE mcp', [], null],
        ['POST mcp', [ReorderJsonAccept::class, AddWwwAuthenticateHeader::class, 'auth:api', 'throttle:mcp'], null],
        ['GET|HEAD .well-known/oauth-protected-resource', [], 'mcp.oauth.protected-resource'],
        ['GET|HEAD .well-known/oauth-authorization-server', [], 'mcp.oauth.authorization-server'],
        ['GET|HEAD .well-known/oauth-protected-resource/{path}', [], 'mcp.oauth.protected-resource.nested'],
        ['GET|HEAD .well-known/oauth-authorization-server/{path}', [], 'mcp.oauth.authorization-server.nested'],
        ['POST oauth/register', [], null],
    ]);

    Route::getRoutes()->refreshNameLookups();

    $this->getJson('/.well-known/oauth-authorization-server')->assertJson(['scopes_supported' => ['mcp:use']]);
    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertJson(['scopes_supported' => ['mcp:use']]);
});
