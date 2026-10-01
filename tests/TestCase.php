<?php

namespace Bcl\Toolkit\Tests;

use Bcl\Toolkit\Tests\Fixtures\ClobberingScopesProvider;
use Bcl\Toolkit\ToolkitServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\SchemalessAttributes\SchemalessAttributesServiceProvider;
use Spatie\Translatable\TranslatableServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testbench does no package auto-discovery, so the vendor providers
     * the toolkit hard-requires are registered by hand here — in a real
     * app composer discovers them.
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActivitylogServiceProvider::class,
            McpServiceProvider::class,
            PassportServiceProvider::class,
            SchemalessAttributesServiceProvider::class,
            TranslatableServiceProvider::class,
            ToolkitServiceProvider::class,
            ClobberingScopesProvider::class,
        ];
    }
}
