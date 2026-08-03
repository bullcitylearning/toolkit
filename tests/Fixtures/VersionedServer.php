<?php

namespace Bcl\Toolkit\Tests\Fixtures;

use Bcl\Toolkit\Mcp\Concerns\ManagesInstructionsVersion;
use Bcl\Toolkit\Mcp\Contracts\HasInstructionsVersion;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('Versioned Fixture')]
#[Instructions('instructions_version: 3 — pass `instructions_version: 3` as a parameter on every tool call. Always frobnicate before you baz.')]
class VersionedServer extends Server implements HasInstructionsVersion
{
    use ManagesInstructionsVersion;

    public const INSTRUCTIONS_VERSION = 3;

    protected array $tools = [
        VersionedTool::class,
    ];

    protected static function currentGuidanceSummary(): string
    {
        return sprintf(
            '(1) always frobnicate before you baz; (2) pass instructions_version: %d on every tool call.',
            self::instructionsVersion(),
        );
    }
}
