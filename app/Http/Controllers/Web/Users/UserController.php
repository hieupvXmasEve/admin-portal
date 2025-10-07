<?php

namespace App\Http\Controllers\Web\Users;

use App\Constants\UserRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Services\RoleAssignmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected RoleAssignmentService $roleAssignmentService
    ) {}

    public function index(Request $request, User $user)
    {
        // Validate input
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'role_id' => 'nullable|integer|exists:roles,id',
        ]);

        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;
        $currentCampusId = session('current_campus_id');

        $query = $user->newQuery()->orderBy('id', 'desc');

        // Global search (name and email)
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (! empty($validated['role_id'])) {
            $query->whereHas('campusRoles', function ($q) use ($validated, $currentCampusId) {
                $q->where('role_id', $validated['role_id'])
                    ->where('campus_id', $currentCampusId);
            });
        }

        // Eager load roles for current campus
        $query->with(['campusRoles' => function ($q) use ($currentCampusId) {
            $q->wherePivot('campus_id', $currentCampusId);
        }]);

        $users = $query->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        // Get all roles for filter dropdown
        $roleController = new \App\Http\Controllers\Web\RoleController(
            new \App\Services\RoleService($this->roleAssignmentService),
            $this->roleAssignmentService
        );
        $roles = $roleController->getRolesWithPermissions();

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'role_id' => $validated['role_id'] ?? null,
            ],
        ]);
    }

    public function create()
    {
        $roleController = new \App\Http\Controllers\Web\RoleController(
            new \App\Services\RoleService($this->roleAssignmentService),
            $this->roleAssignmentService
        );
        $roles = $roleController->getRolesWithPermissions();

        return Inertia::render('users/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->userService->createUser($request->validated());

        return redirect()->route(UserRoutes::INDEX)->with('success', 'User created successfully!');
    }

    public function edit(User $user)
    {
        $roleController = new \App\Http\Controllers\Web\RoleController(
            new \App\Services\RoleService($this->roleAssignmentService),
            $this->roleAssignmentService
        );
        $roles = $roleController->getRolesWithPermissions();

        // Get current campus ID
        $currentCampusId = session('current_campus_id');

        // Get user's current roles for this campus
        $userRoleIds = $user->campusRoles()
            ->where('campus_id', $currentCampusId)
            ->pluck('role_id')
            ->toArray();

        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'userRoleIds' => $userRoleIds,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userService->updateUser($user, $request->validated());

        return redirect()->route(UserRoutes::INDEX)->with('success', 'User updated successfully!');
    }

    public function destroy(User $user)
    {
        $this->userService->deleteUser($user);

        return redirect()->route(UserRoutes::INDEX)->with('success', 'User removed or deleted successfully!');
    }
}
