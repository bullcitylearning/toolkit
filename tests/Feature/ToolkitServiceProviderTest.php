<?php

use Bcl\Toolkit\ToolkitServiceProvider;

it('registers the toolkit service provider', function () {
    expect($this->app->getProviders(ToolkitServiceProvider::class))->toHaveCount(1);
});
