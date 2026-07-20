<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class TokenService
{
    /**
     * Issue a signed, short-lived access token for the given user.
     *
     * @return array{token: string, expires_in: int}
     */
    public function issueAccessToken(User $user): array
    {
        $ttl = (int) config('jwt.access_ttl') * 60;
        $now = now();

        $claims = [
            'iss' => config('jwt.issuer'),
            'sub' => $user->getKey(),
            'iat' => $now->timestamp,
            'exp' => $now->copy()->addSeconds($ttl)->timestamp,
            'jti' => (string) Str::uuid(),
        ];

        return [
            'token' => JWT::encode($claims, config('jwt.secret'), config('jwt.algo')),
            'expires_in' => $ttl,
        ];
    }

    /**
     * Decode and validate an access token. Returns the decoded payload, or
     * null if the token is malformed, tampered with, or expired.
     */
    public function decodeAccessToken(string $jwt): ?object
    {
        JWT::$leeway = (int) config('jwt.leeway');

        try {
            return JWT::decode($jwt, new Key(config('jwt.secret'), config('jwt.algo')));
        } catch (Throwable) {
            return null;
        }
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
            'expires_at' => now()->addMinutes((int) config('jwt.refresh_ttl')),
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
     * Deny-list an access token so it is rejected before its natural expiry
     * (used on logout). The cache entry lives only until the token would have
     * expired anyway, so it self-cleans.
     */
    public function blacklistAccessToken(object $payload): void
    {
        if (! isset($payload->jti, $payload->exp)) {
            return;
        }

        $ttl = (int) $payload->exp - now()->timestamp;

        if ($ttl > 0) {
            Cache::put($this->blacklistKey($payload->jti), true, $ttl);
        }
    }

    /**
     * Whether the given access token id has been deny-listed.
     */
    public function accessTokenIsBlacklisted(string $jti): bool
    {
        return Cache::has($this->blacklistKey($jti));
    }

    protected function blacklistKey(string $jti): string
    {
        return "jwt:blacklist:{$jti}";
    }

    /**
     * Deterministic hash used to look up stored refresh tokens.
     */
    protected function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
