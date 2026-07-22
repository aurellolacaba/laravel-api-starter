<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Create a new user. Authorization (the `users.create` permission) is
     * enforced by StoreUserRequest before this method is reached.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['status'] ??= 'active';

        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create($data);

        if ($roles !== []) {
            $user->syncRoles($roles);
        }

        return response()->json(
            new UserResource($user),
            Response::HTTP_CREATED,
        );
    }

    public function all(): JsonResponse
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters('first_name', 'last_name', 'email')
            ->allowedSorts('first_name', 'last_name', 'email')
            ->paginate(50)
            ->appends(request()->query());

        return UserResource::collection($users)
            ->additional([
                'success' => true,
                'message' => 'Users retrieved successfully.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
