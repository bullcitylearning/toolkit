<?php

namespace Bcl\Toolkit\Mcp\Concerns;

/**
 * Default implementation of the HasInstructionsVersion contract. The server
 * declares an INSTRUCTIONS_VERSION constant — bump it whenever the
 * #[Instructions] text or any behavioral tool guidance changes, and update
 * the version stated in the instructions to match (the packaged
 * InstructionsVersion::assertContract() test keeps the two in sync).
 */
trait ManagesInstructionsVersion
{
    public static function instructionsVersion(): int
    {
        return static::INSTRUCTIONS_VERSION;
    }

    public static function staleInstructionsNotice(mixed $received): string
    {
        $state = $received === null
            ? 'Your call did not include instructions_version, so your guidance may be stale'
            : "Your instructions are outdated (you sent v{$received})";

        return sprintf(
            '%s. Current guidance (v%d): %s',
            $state,
            static::instructionsVersion(),
            static::currentGuidanceSummary(),
        );
    }

    /**
     * Condensed restatement of the load-bearing rules from #[Instructions],
     * delivered through tool results when a client's guidance is stale.
     * End with a reminder to pass instructions_version on every call.
     */
    abstract protected static function currentGuidanceSummary(): string;
}
