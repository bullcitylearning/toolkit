<?php

namespace Bcl\Toolkit\Tests;

use Bcl\Toolkit\ToolkitServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ToolkitServiceProvider::class,
        ];
    }
}
