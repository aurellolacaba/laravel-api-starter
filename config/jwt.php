<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Signing Secret
    |--------------------------------------------------------------------------
    |
    | The secret used to sign and verify access tokens. If JWT_SECRET is not
    | set, we fall back to the application key so the app works out of the box.
    | For HS256 the raw APP_KEY string (including the "base64:" prefix) is a
    | perfectly good HMAC secret.
    |
    */

    'secret' => env('JWT_SECRET', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Signing Algorithm
    |--------------------------------------------------------------------------
    */

    'algo' => env('JWT_ALGO', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | Token Issuer
    |--------------------------------------------------------------------------
    |
    | Value placed in the "iss" claim and validated when decoding.
    |
    */

    'issuer' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Access Token TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | Access tokens are stateless and cannot be revoked before expiry, so keep
    | this short. Defaults to 15 minutes.
    |
    */

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | Refresh tokens are persisted, rotated, and revocable. Defaults to 30 days.
    |
    */

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 43200),

    /*
    |--------------------------------------------------------------------------
    | Clock Leeway (seconds)
    |--------------------------------------------------------------------------
    |
    | Allowance for small clock differences between servers when validating
    | "exp" and "iat" claims.
    |
    */

    'leeway' => (int) env('JWT_LEEWAY', 0),

];
