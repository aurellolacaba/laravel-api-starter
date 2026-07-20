<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stateless JWT guard: resolve the user from the Bearer access token.
        Auth::viaRequest('jwt', function (Request $request): ?User {
            $jwt = $request->bearerToken();

            if ($jwt === null) {
                return null;
            }

            $tokens = app(TokenService::class);
            $payload = $tokens->decodeAccessToken($jwt);

            if ($payload === null || ! isset($payload->sub)) {
                return null;
            }

            // Reject tokens revoked via logout before their natural expiry.
            if (isset($payload->jti) && $tokens->accessTokenIsBlacklisted($payload->jti)) {
                return null;
            }

            return User::find($payload->sub);
        });
    }
}
