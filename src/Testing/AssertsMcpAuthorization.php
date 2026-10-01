<?php

namespace Bcl\Toolkit\Testing;

use Bcl\Toolkit\Mcp\GatedTool;
use Bcl\Toolkit\Mcp\ToolAudit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Assert;
use ReflectionProperty;
use Spatie\Activitylog\Models\Activity;

/**
 * Proves the MCP authorization standard in an app's own suite:
 *
 *     uses(AssertsMcpAuthorization::class);
 *
 *     $this->assertEveryToolGated(AdminServer::class);
 *     $this->assertToolHiddenFrom(AppServer::class, ApproveSendTool::class, $plainUser);
 *
 * $tool is a tool class or its MCP name. The hidden/visible checks read the
 * tool list the way tools/list does; the refusal check makes a real
 * tools/call through laravel/mcp's test harness.
 */
trait AssertsMcpAuthorization
{
    /**
     * @param  class-string<Server>  $server
     */
    public function assertToolHiddenFrom(string $server, string $tool, Authenticatable $user): void
    {
        Assert::assertNotContains(
            $this->mcpToolName($server, $tool),
            $this->mcpToolNamesListedTo($server, $user),
            "Tool [{$tool}] should be hidden from this user on [{$server}].",
        );
    }

    /**
     * @param  class-string<Server>  $server
     */
    public function assertToolVisibleTo(string $server, string $tool, Authenticatable $user): void
    {
        Assert::assertContains(
            $this->mcpToolName($server, $tool),
            $this->mcpToolNamesListedTo($server, $user),
            "Tool [{$tool}] should be listed for this user on [{$server}].",
        );
    }

    /**
     * A direct tools/call is refused with the standard envelope, even
     * though the tool is hidden from the list.
     *
     * @param  class-string<Server>  $server
     * @param  array<string, mixed>  $arguments
     */
    public function assertToolRefusedFor(string $server, string $tool, Authenticatable $user, array $arguments = []): void
    {
        $instance = $this->mcpTool($server, $tool);

        Assert::assertInstanceOf(GatedTool::class, $instance, "Tool [{$tool}] is not a GatedTool.");

        $server::actingAs($user)
            ->tool($instance, $arguments)
            ->assertHasErrors([json_encode(
                ['error' => 'forbidden', 'capability' => $instance->requiredCapability()],
                JSON_UNESCAPED_SLASHES,
            )]);
    }

    /**
     * The latest mcp audit row for this tool matches. $outcome is ok,
     * error or forbidden; $user, when given, must be the causer.
     */
    public function assertToolAudited(string $server, string $tool, ?Authenticatable $user = null, ?string $outcome = null): void
    {
        $name = $this->mcpToolName($server, $tool);

        $activity = Activity::query()
            ->where('log_name', ToolAudit::LOG_NAME)
            ->latest('id')
            ->get()
            ->first(fn (Activity $a): bool => $a->properties->get('tool') === $name);

        Assert::assertNotNull($activity, "No mcp audit row for tool [{$name}].");
        Assert::assertSame($server, $activity->properties->get('server'), 'Audit row names a different server.');

        if ($outcome !== null) {
            Assert::assertSame($outcome, $activity->properties->get('outcome'));
            Assert::assertSame($outcome === 'forbidden' ? 'tool.refused' : 'tool.called', $activity->event);
        }

        if ($user !== null) {
            Assert::assertTrue(
                $activity->causer?->is($user) ?? false,
                'Audit row is not caused by the expected user.',
            );
        }
    }

    /**
     * Every tool registered on the server declares a capability — the
     * guard for an AdminServer.
     *
     * @param  class-string<Server>  $server
     */
    public function assertEveryToolGated(string $server): void
    {
        foreach ($this->mcpRegisteredTools($server) as $tool) {
            Assert::assertInstanceOf(
                GatedTool::class,
                $tool,
                sprintf('Tool [%s] on [%s] is not a GatedTool.', $tool->name(), $server),
            );
        }
    }

    /**
     * @param  class-string<Server>  $server
     * @return list<string>
     */
    protected function mcpToolNamesListedTo(string $server, Authenticatable $user): array
    {
        $this->app['auth']->guard()->setUser($user);

        return $this->mcpServer($server)
            ->createContext()
            ->tools()
            ->map(fn (Tool $tool): string => $tool->name())
            ->values()
            ->all();
    }

    /**
     * @param  class-string<Server>  $server
     * @return Collection<int, Tool>
     */
    protected function mcpRegisteredTools(string $server): Collection
    {
        $tools = (new ReflectionProperty(Server::class, 'tools'))->getValue($this->mcpServer($server));

        return collect($tools)->map(fn (Tool|string $tool): Tool => is_string($tool) ? $this->app->make($tool) : $tool);
    }

    /**
     * @param  class-string<Server>  $server
     */
    protected function mcpTool(string $server, string $tool): Tool
    {
        $found = $this->mcpRegisteredTools($server)
            ->first(fn (Tool $t): bool => $t::class === $tool || $t->name() === $tool);

        Assert::assertNotNull($found, "Tool [{$tool}] is not registered on [{$server}].");

        return $found;
    }

    /**
     * @param  class-string<Server>  $server
     */
    protected function mcpToolName(string $server, string $tool): string
    {
        return $this->mcpTool($server, $tool)->name();
    }

    /**
     * @param  class-string<Server>  $server
     */
    protected function mcpServer(string $server): Server
    {
        return $this->app->make($server, ['transport' => new FakeTransporter]);
    }
}
