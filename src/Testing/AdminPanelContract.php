<?php

namespace Bcl\Toolkit\Testing;

use Illuminate\Foundation\Testing\TestCase;
use Laravel\Passport\ClientRepository;

/**
 * The canonical admin-panel access behaviors every BCL app upholds. Each
 * app's AdminAccessTest invokes these as one-liners; the user model comes
 * from auth.providers.users.model and must have a factory.
 */
class AdminPanelContract
{
    public static function assertAllowsAnyUserWithoutAllowlist(TestCase $test): void
    {
        config(['filament-socialite.domain_allowlist' => []]);

        $user = static::userModel()::factory()->create(['email' => 'someone@example.com']);

        \PHPUnit\Framework\Assert::assertTrue($user->canAccessPanel(filament()->getPanel('admin')));
    }

    public static function assertGatesPanelByDomainAllowlist(TestCase $test): void
    {
        config(['filament-socialite.domain_allowlist' => ['allowed-org.test']]);

        $staff = static::userModel()::factory()->create(['email' => 'staff@allowed-org.test']);
        $outsider = static::userModel()::factory()->create(['email' => 'intruder@gmail.com']);

        $panel = filament()->getPanel('admin');

        \PHPUnit\Framework\Assert::assertTrue($staff->canAccessPanel($panel));
        \PHPUnit\Framework\Assert::assertFalse($outsider->canAccessPanel($panel));
    }

    /**
     * The testing environment is non-local, so this exercises the
     * production (SSO-only) login page branch.
     */
    public static function assertSsoOnlyLoginPage(TestCase $test): void
    {
        $test->get('/admin/login')
            ->assertOk()
            ->assertSee('Microsoft')
            ->assertDontSee('type="password"', escape: false);
    }

    public static function assertRejectsUnauthenticatedMcp(TestCase $test, string $path = '/mcp'): void
    {
        $test->postJson($path, ['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1])
            ->assertUnauthorized();
    }

    public static function assertRedirectsOauthGuestsToLogin(TestCase $test): void
    {
        $client = app(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('MCP Test Client', ['https://example.com/callback'], confidential: false);

        $query = http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://example.com/callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'test-verifier', true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);

        $test->get("/oauth/authorize?{$query}")
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    protected static function userModel(): string
    {
        return config('auth.providers.users.model');
    }
}
