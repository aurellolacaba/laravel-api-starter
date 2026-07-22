<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Services\TokenService;
use Tests\TestCase;

class RefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_refresh_token_returns_a_new_pair(): void
    {
        $user = User::factory()->create();
        $refresh = app(TokenService::class)->issueRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);
    }

    public function test_a_rotated_refresh_token_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $refresh = app(TokenService::class)->issueRefreshToken($user);

        // First use succeeds and rotates the token.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])->assertOk();

        // Reusing the now-revoked token is rejected.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])->assertUnauthorized();
    }

    public function test_an_unknown_refresh_token_is_unauthorized(): void
    {
        $this->postJson('/api/auth/refresh', ['refresh_token' => 'nope'])
            ->assertUnauthorized();
    }
}
