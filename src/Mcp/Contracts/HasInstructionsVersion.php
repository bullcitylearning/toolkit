<?php

namespace Bcl\Toolkit\Mcp\Contracts;

/**
 * An MCP server that versions its #[Instructions] prose. Clients echo the
 * version on every tool call; tools flag stale guidance inside results —
 * the only reliable back-channel, since a client cannot rewrite its system
 * prompt mid-conversation.
 */
interface HasInstructionsVersion
{
    public static function instructionsVersion(): int;

    public static function staleInstructionsNotice(mixed $received): string;
}
