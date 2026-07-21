<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token_pair_and_user(): void
    {
        $user = User::factory()->create(['email' => 'a@b.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'a@b.com',
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email'],
                'roles',
                'permissions',
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseCount('refresh_tokens', 1);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'a@b.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'a@b.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_inactive_account_is_forbidden(): void
    {
        User::factory()->create(['email' => 'a@b.com', 'status' => 'suspended']);

        $this->postJson('/api/auth/login', [
            'email' => 'a@b.com',
            'password' => 'secret',
        ])->assertStatus(403);
    }
}
