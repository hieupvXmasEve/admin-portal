<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(User::with(['roles', 'campus'])->paginate());
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = $this->userService->createUser($request->validated());
        return new UserResource($user);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['roles', 'campus']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated());
        return new UserResource($updatedUser);
    }

    public function destroy(User $user): \Illuminate\Http\Response
    {
        $this->userService->deleteUser($user);
        return response()->noContent();
    }
}
