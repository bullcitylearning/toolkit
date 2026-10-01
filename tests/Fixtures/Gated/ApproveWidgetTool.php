<?php

namespace Bcl\Toolkit\Tests\Fixtures\Gated;

use Bcl\Toolkit\Mcp\GatedTool;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use RuntimeException;

#[Name('approve_widget')]
class ApproveWidgetTool extends GatedTool
{
    protected function capability(): string
    {
        return 'widgets.approve';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'widget' => $schema->string(),
            'fail' => $schema->boolean(),
        ];
    }

    /**
     * Injects a service after the request, as tool authors may.
     */
    public function perform(Request $request, Repository $config): Response
    {
        if ($request->get('fail')) {
            throw new RuntimeException('widget exploded');
        }

        return $this->respond($request, ['approved' => $request->get('widget'), 'app' => $config->get('app.name')]);
    }
}
