<?php

namespace Bcl\Toolkit\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

/**
 * Stands in for an app provider (baton, fileclerk) that boots after the
 * toolkit and replaces the Passport scope list wholesale.
 */
class ClobberingScopesProvider extends ServiceProvider
{
    public function boot(): void
    {
        Passport::tokensCan(['context:herd' => 'Act in herd']);
    }
}
