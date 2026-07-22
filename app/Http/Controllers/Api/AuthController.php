<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * Authenticate with credentials and issue an access + refresh token pair.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user === null || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'This account is not active.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            // 'permissions' => $user->getPermissionNames(),
            ...$this->tokens->issuePair($user, $request),
        ]);
    }

    /**
     * Exchange a valid refresh token for a new token pair (with rotation).
     */
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $pair = $this->tokens->rotateRefreshToken($request->refresh_token, $request);

        if ($pair === null) {
            return response()->json([
                'message' => 'The refresh token is invalid or expired.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($pair);
    }

    /**
     * Revoke the given refresh token and immediately delete the current access
     * token so it can no longer be used.
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->tokens->revokeRefreshToken($request->refresh_token);

        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
