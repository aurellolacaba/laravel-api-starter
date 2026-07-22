<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Services\TokenService;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_refresh_token(): void
    {
        $user = User::factory()->create();
        $tokens = app(TokenService::class);
        $access = $tokens->issueAccessToken($user)['token'];
        $refresh = $tokens->issueRefreshToken($user);

        $this->postJson('/api/auth/logout', ['refresh_token' => $refresh], [
            'Authorization' => "Bearer {$access}",
        ])->assertOk();

        // The revoked refresh token can no longer mint new tokens.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])
            ->assertUnauthorized();
    }

    public function test_logout_immediately_invalidates_the_access_token(): void
    {
        $user = User::factory()->create();
        $tokens = app(TokenService::class);
        $access = $tokens->issueAccessToken($user)['token'];
        $refresh = $tokens->issueRefreshToken($user);

        // The access token works before logout.
        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$access}"])->assertOk();

        $this->postJson('/api/auth/logout', ['refresh_token' => $refresh], [
            'Authorization' => "Bearer {$access}",
        ])->assertOk();

        // Simulate a fresh request/process: the AuthManager is a singleton
        // across requests in a single test, and the request guard memoizes the
        // user it already resolved during logout. In production every request
        // is a new process, so forget the resolved guards here.
        $this->app['auth']->forgetGuards();

        // Same access token is now rejected.
        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$access}"])
            ->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout', ['refresh_token' => 'whatever'])
            ->assertUnauthorized();
    }
}
