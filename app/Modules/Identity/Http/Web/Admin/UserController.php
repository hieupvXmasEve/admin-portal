<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\CreateUserAction;
use App\Modules\Identity\Actions\DeleteUserAction;
use App\Modules\Identity\Actions\UpdateUserAction;
use App\Modules\Identity\Http\Requests\Identity\ListUsersRequest;
use App\Modules\Identity\Http\Requests\Identity\StoreUserRequest;
use App\Modules\Identity\Http\Requests\Identity\UpdateUserRequest;
use App\Modules\Identity\Queries\GetUsersQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private GetUsersQuery $getUsersQuery
    ) {}

    public function index(ListUsersRequest $request): Response
    {
        $validated = $request->validated();
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 10;

        $filters = [
            'search' => $validated['search'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
            'type' => $validated['type'] ?? null,
        ];

        $users = $this->getUsersQuery->handle($filters, $perPage, $page);
        $roles = $this->getUsersQuery->getRoles();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $roles = $this->getUsersQuery->getRoles();

        return Inertia::render('Users/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        CreateUserAction::run($request->validated());

        return redirect()
            ->route('identity.users.index')
            ->with('success', 'User created successfully!');
    }

    public function show(User $user): Response
    {
        $user->load(['department']);

        return Inertia::render('Users/Show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): Response
    {
        $roles = $this->getUsersQuery->getRoles();
        $userRoleIds = $this->getUsersQuery->getUserRoleIds($user);

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'userRoleIds' => $userRoleIds,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        UpdateUserAction::run($user, $request->validated());

        return redirect()
            ->route('identity.users.index')
            ->with('success', 'User updated successfully!');
    }

    public function destroy(User $user): RedirectResponse
    {
        DeleteUserAction::run($user);

        return redirect()
            ->route('identity.users.index')
            ->with('success', 'User removed or deleted successfully!');
    }
}
