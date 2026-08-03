<?php

namespace Bcl\Toolkit\Testing;

use Bcl\Toolkit\Mcp\Contracts\HasInstructionsVersion;
use Illuminate\Container\Container;
use Laravel\Mcp\Server\Attributes\Instructions;
use PHPUnit\Framework\Assert;
use ReflectionClass;

class InstructionsVersion
{
    /**
     * Assert a server upholds the instructions-version contract: the version
     * stated in its #[Instructions] prose matches the constant, and every
     * registered tool exposes the instructions_version parameter.
     *
     * @param  class-string  $serverClass
     */
    public static function assertContract(string $serverClass): void
    {
        Assert::assertTrue(
            is_a($serverClass, HasInstructionsVersion::class, true),
            "[{$serverClass}] must implement ".HasInstructionsVersion::class.'.',
        );

        $reflection = new ReflectionClass($serverClass);

        $attributes = $reflection->getAttributes(Instructions::class);
        Assert::assertNotEmpty($attributes, "[{$serverClass}] has no #[Instructions] attribute.");

        $version = $serverClass::instructionsVersion();

        Assert::assertStringContainsString(
            'instructions_version: '.$version,
            $attributes[0]->newInstance()->value,
            "[{$serverClass}] instructions prose does not state instructions_version: {$version}.",
        );

        $tools = $reflection->getDefaultProperties()['tools'] ?? [];
        Assert::assertNotEmpty($tools, "[{$serverClass}] registers no tools.");

        foreach ($tools as $toolClass) {
            $schema = Container::getInstance()->make($toolClass)->toArray()['inputSchema'];

            Assert::assertArrayHasKey(
                'instructions_version',
                (array) ($schema['properties'] ?? []),
                "[{$toolClass}] does not expose the instructions_version parameter.",
            );
        }
    }
}
