<?php

namespace Bcl\Toolkit\Tests\Fixtures;

use Bcl\Toolkit\Mcp\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('versioned_tool')]
class VersionedTool extends Tool
{
    protected function instructionsVersionServer(): ?string
    {
        return VersionedServer::class;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'thing' => $schema->string()->description('A thing.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, ['ok' => true]);
    }
}
