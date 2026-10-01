<?php

namespace Bcl\Toolkit\Tests\Fixtures\Gated;

use Bcl\Toolkit\Mcp\GatedTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('purge_everything')]
class PurgeEverythingTool extends GatedTool
{
    protected function capability(): string
    {
        return 'widgets.purge';
    }

    public function perform(Request $request): Response
    {
        return $this->respond($request, ['purged' => true]);
    }
}
