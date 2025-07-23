<?php

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Constants\UserRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request, User $user)
    {
        // Validate input
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'filter.name' => 'string|max:255',
            'filter.email' => 'string|max:255',
        ]);

        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;

        $query = $user->newQuery()->orderBy('id', 'desc');

        // Global search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Column filters
        if (!empty($validated['filter'])) {
            foreach ($validated['filter'] as $column => $value) {
                if (!empty($value) && in_array($column, ['name', 'email'])) {
                    $query->where($column, 'like', "%{$value}%");
                }
            }
        }

        $users = $query->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'name' => $validated['filter']['name'] ?? null,
                'email' => $validated['filter']['email'] ?? null,
            ],
        ]);
    }

    public function create()
    {
        $roleController = new \App\Http\Controllers\Web\RoleController(new \App\Services\RoleService());
        $roles = $roleController->getRolesWithPermissions();

        return Inertia::render('users/Create', [
            'roles' => $roles
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->userService->createUser($request->validated());
        return redirect()->route(UserRoutes::INDEX)->with('success', 'User created successfully!');
    }

    public function edit(User $user)
    {
        $roleController = new \App\Http\Controllers\Web\RoleController(new \App\Services\RoleService());
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
            'userRoleIds' => $userRoleIds
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
