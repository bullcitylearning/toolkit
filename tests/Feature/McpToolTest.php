<?php

use Bcl\Toolkit\Mcp\Tool;
use Carbon\CarbonImmutable;
use Laravel\Mcp\Request;

function makeTool(): Tool
{
    return new class extends Tool
    {
        public function callRespond(Request $request, array $payload)
        {
            return $this->respond($request, $payload);
        }

        public function callParseDateTime(?string $value, string $field): ?CarbonImmutable
        {
            return $this->parseDateTime($value, $field);
        }
    };
}

it('responds with a json envelope', function () {
    $response = makeTool()->callRespond(new Request, ['ok' => true]);

    expect($response->content()->toArray()['text'])->toBe(json_encode(['ok' => true]));
});

it('parses ISO-8601 datetimes to UTC', function () {
    $parsed = makeTool()->callParseDateTime('2026-08-15T09:00:00-04:00', 'send_at');

    expect($parsed)->toBeInstanceOf(CarbonImmutable::class)
        ->and($parsed->toIso8601String())->toBe('2026-08-15T13:00:00+00:00');
});

it('returns null for empty datetime values', function () {
    expect(makeTool()->callParseDateTime(null, 'send_at'))->toBeNull()
        ->and(makeTool()->callParseDateTime('', 'send_at'))->toBeNull();
});

it('rejects unparseable datetimes with the field name', function () {
    makeTool()->callParseDateTime('not-a-date', 'send_at');
})->throws(InvalidArgumentException::class, 'send_at must be an ISO-8601 datetime');
