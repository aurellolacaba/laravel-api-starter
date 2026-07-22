<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenService
{
    /**
     * Issue a short-lived Sanctum access token for the given user.
     *
     * @return array{token: string, expires_in: int}
     */
    public function issueAccessToken(User $user): array
    {
        $ttl = (int) config('sanctum.expiration');
        $expiresAt = $ttl > 0 ? now()->addMinutes($ttl) : null;

        $token = $user->createToken('access', ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'expires_in' => $ttl * 60,
        ];
    }

    /**
     * Create and persist a new refresh token, returning the plaintext value
     * (only the SHA-256 hash is stored).
     */
    public function issueRefreshToken(User $user, ?Request $request = null): string
    {
        $plain = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->getKey(),
            'token_hash' => $this->hash($plain),
            'expires_at' => now()->addMinutes((int) config('sanctum.refresh_ttl')),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        return $plain;
    }

    /**
     * Issue a fresh access + refresh token pair for a user.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issuePair(User $user, ?Request $request = null): array
    {
        $access = $this->issueAccessToken($user);

        return [
            'access_token' => $access['token'],
            'refresh_token' => $this->issueRefreshToken($user, $request),
            'token_type' => 'Bearer',
            'expires_in' => $access['expires_in'],
        ];
    }

    /**
     * Validate a refresh token and rotate it: the presented token is revoked
     * and a brand new pair is issued. Returns null when the token is unknown,
     * expired, or already revoked.
     *
     * Reuse detection: presenting an already-revoked (but known) token means
     * it has likely been stolen, so every refresh token for that user is
     * revoked as a precaution.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function rotateRefreshToken(string $plain, ?Request $request = null): ?array
    {
        $token = RefreshToken::where('token_hash', $this->hash($plain))->first();

        if ($token === null || $token->expires_at->isPast()) {
            return null;
        }

        if ($token->revoked_at !== null) {
            // Reuse of a revoked token — revoke the whole family.
            RefreshToken::where('user_id', $token->user_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return null;
        }

        $user = $token->user;

        if ($user === null) {
            return null;
        }

        $token->update(['revoked_at' => now()]);

        return $this->issuePair($user, $request);
    }

    /**
     * Revoke a refresh token by its plaintext value (used on logout). Silently
     * does nothing if the token is unknown or already revoked.
     */
    public function revokeRefreshToken(string $plain): void
    {
        RefreshToken::where('token_hash', $this->hash($plain))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Deterministic hash used to look up stored refresh tokens.
     */
    protected function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
