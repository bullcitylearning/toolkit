<?php

namespace Bcl\Toolkit\Tests\Fixtures\Gated;

use Bcl\Toolkit\Tests\Fixtures\VersionedTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('App Fixture')]
class AppFixtureServer extends Server
{
    protected array $tools = [
        VersionedTool::class,
        ApproveWidgetTool::class,
        UndefinedCapabilityTool::class,
    ];
}
