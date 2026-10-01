<?php

namespace Bcl\Toolkit\Tests\Fixtures\Gated;

use Bcl\Toolkit\Mcp\GatedTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('undefined_capability')]
class UndefinedCapabilityTool extends GatedTool
{
    protected function capability(): string
    {
        return 'nobody.defined.this';
    }

    public function perform(Request $request): Response
    {
        return $this->respond($request, ['ok' => true]);
    }
}
