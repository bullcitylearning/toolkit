<?php

namespace Bcl\Toolkit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class McpTokenCommand extends Command
{
    protected $signature = 'bcl:mcp-token
        {name=mcp-server : Token name (the acting identity, e.g. claude-cowork-jim)}
        {--driver=passport : passport (OAuth PAT for the canonical /mcp endpoint) or sanctum (ability-scoped PAT)}
        {--user= : Email of the owning user (defaults to the first user)}
        {--ability=* : Sanctum abilities to bake in (e.g. org:bcl, context:herd)}
        {--scope=* : Passport scopes (defaults to mcp:use)}
        {--path=/mcp : MCP endpoint path for the printed config snippet}';

    protected $description = 'Issue an MCP access token and print it once, with a ready-to-paste mcpServers config snippet';

    public function handle(): int
    {
        $model = config('auth.providers.users.model');

        $user = $this->option('user')
            ? $model::where('email', $this->option('user'))->first()
            : $model::query()->orderBy('id')->first();

        if (! $user) {
            $this->error('No user found to own the token. Create one first (php artisan make:filament-user).');

            return self::FAILURE;
        }

        $driver = $this->option('driver');
        $name = $this->argument('name');

        $plainText = match ($driver) {
            'passport' => $user->createPassportToken($name, $this->option('scope') ?: ['mcp:use'])->accessToken,
            'sanctum' => $user->createToken($name, $this->option('ability') ?: ['*'])->plainTextToken,
            default => null,
        };

        if ($plainText === null) {
            $this->error("Unknown driver [{$driver}] — use passport or sanctum.");

            return self::FAILURE;
        }

        $this->info("MCP token ({$driver}) issued for {$user->email}".
            ($driver === 'sanctum' && $this->option('ability') ? ' scoped to: '.implode(', ', $this->option('ability')) : ''));
        $this->newLine();
        $this->line('Token (save this — it will not be shown again):');
        $this->line('  '.$plainText);
        $this->newLine();
        $this->line('Example MCP client config:');
        $this->line(json_encode([
            'mcpServers' => [
                Str::slug(config('app.name')) => [
                    'type' => 'url',
                    'url' => url($this->option('path')),
                    'headers' => [
                        'Authorization' => 'Bearer '.$plainText,
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();

        return self::SUCCESS;
    }
}
