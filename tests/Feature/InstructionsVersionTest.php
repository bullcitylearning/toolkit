<?php

use Bcl\Toolkit\Tests\Fixtures\VersionedServer;
use Bcl\Toolkit\Tests\Fixtures\VersionedTool;
use Laravel\Mcp\Request;

function versionedToolText(?int $sent): string
{
    $arguments = $sent === null ? [] : ['instructions_version' => $sent];

    return (new VersionedTool)
        ->handle(new Request($arguments))
        ->content()
        ->toArray()['text'];
}

it('auto-adds the instructions_version parameter to versioned tool schemas', function () {
    $schema = (new VersionedTool)->toArray()['inputSchema'];

    expect((array) $schema['properties'])
        ->toHaveKey('thing')
        ->toHaveKey('instructions_version');
});

it('leaves unversioned tool schemas alone', function () {
    $tool = new class extends Bcl\Toolkit\Mcp\Tool {};

    expect((array) ($tool->toArray()['inputSchema']['properties'] ?? []))
        ->not->toHaveKey('instructions_version');
});

it('appends a notice when instructions_version is missing', function () {
    expect(versionedToolText(null))
        ->toContain('instructions_notice')
        ->toContain('did not include instructions_version')
        ->toContain('frobnicate');
});

it('appends a correction when the echoed version is stale', function () {
    expect(versionedToolText(1))
        ->toContain('outdated (you sent v1)')
        ->toContain('Current guidance (v3)');
});

it('stays quiet when the current version is echoed', function () {
    expect(versionedToolText(VersionedServer::INSTRUCTIONS_VERSION))
        ->not->toContain('instructions_notice');
});

it('passes the packaged contract assertion for a conforming server', function () {
    Bcl\Toolkit\Testing\InstructionsVersion::assertContract(VersionedServer::class);

    expect(true)->toBeTrue();
});

it('fails the contract assertion for a non-conforming server', function () {
    Bcl\Toolkit\Testing\InstructionsVersion::assertContract(Laravel\Mcp\Server::class);
})->throws(PHPUnit\Framework\AssertionFailedError::class);
