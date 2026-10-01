<?php

namespace Bcl\Toolkit\Tests\Fixtures\Gated;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('Admin Fixture')]
class AdminFixtureServer extends Server
{
    protected array $tools = [
        PurgeEverythingTool::class,
    ];
}
