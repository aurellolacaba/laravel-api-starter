<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Services\TokenService;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_access_token_returns_the_current_user(): void
    {
        $user = User::factory()->create();
        $token = app(TokenService::class)->issueAccessToken($user)['token'];

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_a_missing_token_is_unauthorized(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_an_invalid_token_is_unauthorized(): void
    {
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer not-a-real-token'])
            ->assertUnauthorized();
    }
}
