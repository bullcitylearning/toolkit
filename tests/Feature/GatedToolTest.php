<?php

use Bcl\Toolkit\Mcp\Methods\CallTool;
use Bcl\Toolkit\Mcp\ToolAudit;
use Bcl\Toolkit\Testing\AssertsMcpAuthorization;
use Bcl\Toolkit\Tests\Fixtures\Gated\AdminFixtureServer;
use Bcl\Toolkit\Tests\Fixtures\Gated\AppFixtureServer;
use Bcl\Toolkit\Tests\Fixtures\Gated\ApproveWidgetTool;
use Bcl\Toolkit\Tests\Fixtures\Gated\UndefinedCapabilityTool;
use Bcl\Toolkit\Tests\Fixtures\Gated\User;
use Bcl\Toolkit\Tests\Fixtures\VersionedServer;
use Bcl\Toolkit\Tests\Fixtures\VersionedTool;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\CallTool as BaseCallTool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Activitylog\Models\Activity;

uses(AssertsMcpAuthorization::class);

beforeEach(function () {
    (require __DIR__.'/../../vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub')->up();

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    config(['auth.providers.users.model' => User::class]);

    Gate::define('widgets.approve', fn (User $user): bool => $user->email === 'approver@example-org.test');

    $this->plain = User::create(['name' => 'Plain', 'email' => 'plain@example-org.test', 'password' => 'x']);
    $this->approver = User::create(['name' => 'Approver', 'email' => 'approver@example-org.test', 'password' => 'x']);
});

it('binds the toolkit CallTool in place of laravel/mcp\'s', function () {
    expect(app(BaseCallTool::class))->toBeInstanceOf(CallTool::class);
});

it('hides and refuses a gated tool for a user without the capability', function () {
    $this->assertToolHiddenFrom(AppFixtureServer::class, ApproveWidgetTool::class, $this->plain);
    $this->assertToolRefusedFor(AppFixtureServer::class, 'approve_widget', $this->plain, ['widget' => 'w1']);
    $this->assertToolAudited(AppFixtureServer::class, ApproveWidgetTool::class, $this->plain, 'forbidden');
});

it('lists and runs a gated tool for a user with the capability', function () {
    $this->assertToolVisibleTo(AppFixtureServer::class, ApproveWidgetTool::class, $this->approver);

    AppFixtureServer::actingAs($this->approver)
        ->tool(ApproveWidgetTool::class, ['widget' => 'w1'])
        ->assertHasNoErrors()
        ->assertSee('"approved":"w1"');

    $this->assertToolAudited(AppFixtureServer::class, ApproveWidgetTool::class, $this->approver, 'ok');
});

it('refuses with the standard envelope and no exception', function () {
    AppFixtureServer::actingAs($this->plain)
        ->tool(ApproveWidgetTool::class, ['widget' => 'w1'])
        ->assertHasErrors(['{"error":"forbidden","capability":"widgets.approve"}']);
});

it('denies a capability nobody defined, even to a capable user', function () {
    $this->assertToolHiddenFrom(AppFixtureServer::class, UndefinedCapabilityTool::class, $this->approver);
    $this->assertToolRefusedFor(AppFixtureServer::class, UndefinedCapabilityTool::class, $this->approver);
});

it('refuses and audits a guest', function () {
    expect(app(AppFixtureServer::class, ['transport' => new FakeTransporter])
        ->createContext()->tools()->map->name()->all())->not->toContain('approve_widget');

    AppFixtureServer::tool(ApproveWidgetTool::class)
        ->assertHasErrors(['"error":"forbidden"']);

    $activity = Activity::query()->sole();

    expect($activity->causer_id)->toBeNull()
        ->and($activity->properties->get('auth'))->toBeNull();
});

it('audits a call that throws as an error and rethrows', function () {
    AppFixtureServer::actingAs($this->approver)
        ->tool(ApproveWidgetTool::class, ['widget' => 'w1', 'fail' => true])
        ->assertHasErrors();

    $this->assertToolAudited(AppFixtureServer::class, ApproveWidgetTool::class, $this->approver, 'error');
});

it('writes the documented audit shape', function () {
    AppFixtureServer::actingAs($this->approver)->tool(ApproveWidgetTool::class, ['widget' => 'w1']);

    $activity = Activity::query()->sole();

    expect($activity->log_name)->toBe('mcp')
        ->and($activity->event)->toBe('tool.called')
        ->and($activity->causer->is($this->approver))->toBeTrue()
        ->and($activity->properties->all())->toBe([
            'server' => AppFixtureServer::class,
            'tool' => 'approve_widget',
            'capability' => 'widgets.approve',
            'auth' => 'session',
            'client_id' => null,
            'token_id' => null,
            'outcome' => 'ok',
            'arguments' => ['widget' => 'w1'],
        ]);
});

it('records the passport client id', function () {
    $client = (new Client)->forceFill(['id' => '019a-client']);

    Passport::actingAs($this->approver, ['mcp:use'], 'web', $client);

    AppFixtureServer::tool(ApproveWidgetTool::class, ['widget' => 'w1']);

    expect(Activity::query()->sole()->properties->only(['auth', 'client_id'])->all())
        ->toBe(['auth' => 'passport', 'client_id' => '019a-client']);
});

it('redacts secret-shaped argument keys, nested too', function () {
    AppFixtureServer::actingAs($this->approver)->tool(ApproveWidgetTool::class, [
        'widget' => 'w1',
        'api_token' => 'tok',
        'Password' => 'pw',
        'client_secret' => 's',
        'apiKey' => 'k',
        'credentials' => ['user' => 'u'],
        'Authorization' => 'Bearer x',
        'cookie_jar' => 'c',
        'nested' => ['passphrase' => 'p', 'keep' => 'me'],
    ]);

    expect(Activity::query()->sole()->properties->get('arguments'))->toBe([
        'widget' => 'w1',
        'api_token' => '[redacted]',
        'Password' => '[redacted]',
        'client_secret' => '[redacted]',
        'apiKey' => '[redacted]',
        'credentials' => '[redacted]',
        'Authorization' => '[redacted]',
        'cookie_jar' => '[redacted]',
        'nested' => ['passphrase' => '[redacted]', 'keep' => 'me'],
    ]);
});

it('redacts without touching list values', function () {
    expect(ToolAudit::redact(['ids' => [1, 2], 'secret' => 'x']))->toBe(['ids' => [1, 2], 'secret' => '[redacted]']);
});

it('leaves an ungated tool unchanged: same schema, same output, no audit row', function () {
    $tool = new VersionedTool;

    expect($tool->toArray()['inputSchema']['properties'])->toBe([
        'thing' => ['description' => 'A thing.', 'type' => 'string'],
        'instructions_version' => ['type' => 'integer', 'description' => VersionedTool::INSTRUCTIONS_VERSION_PARAM_DESCRIPTION],
    ]);

    foreach ([VersionedServer::class, AppFixtureServer::class] as $server) {
        $server::actingAs($this->plain)
            ->tool(VersionedTool::class, ['thing' => 'x', 'instructions_version' => 3])
            ->assertHasNoErrors()
            ->assertSee('{"ok":true}');

        $this->assertToolVisibleTo($server, 'versioned_tool', $this->plain);
    }

    expect(Activity::query()->count())->toBe(0);
});

it('still reports an unknown tool as not found', function () {
    $response = (fn () => $this->runMethodHandle(
        new JsonRpcRequest(1, 'tools/call', ['name' => 'no_such_tool']),
        $this->createContext(),
    ));

    expect(fn () => $response->call(app(AppFixtureServer::class, ['transport' => new FakeTransporter])))
        ->toThrow(JsonRpcException::class, 'Tool [no_such_tool] not found.');
});

it('passes the guard helper only when every tool is gated', function () {
    $this->assertEveryToolGated(AdminFixtureServer::class);

    expect(fn () => $this->assertEveryToolGated(AppFixtureServer::class))
        ->toThrow(AssertionFailedError::class, 'versioned_tool');
});

it('keeps /mcp-side tools off the admin server and vice versa', function () {
    Gate::define('widgets.purge', fn (): bool => true);

    $this->assertToolVisibleTo(AdminFixtureServer::class, 'purge_everything', $this->approver);

    expect(fn () => $this->assertToolVisibleTo(AppFixtureServer::class, 'purge_everything', $this->approver))
        ->toThrow(AssertionFailedError::class, 'not registered');
});
