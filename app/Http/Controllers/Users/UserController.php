<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserController extends Controller
{
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
            'users' => Inertia::deepMerge($users),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'name' => $validated['filter']['name'] ?? null,
                'email' => $validated['filter']['email'] ?? null,
            ],
        ]);
    }

    public function create()
    {
        $roleController = new \App\Http\Controllers\RoleController();
        $roles = $roleController->getRolesWithPermissions();

        return Inertia::render('users/Add', [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Store request data:', $request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'selectedRoles' => 'array',
            'selectedRoles.*' => 'exists:roles,id',
        ]);

        // Create the user with a default password (you might want to generate a random one)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make('password123'), // Default password - should be changed on first login
        ]);

        // Assign roles to the user for the current campus
        $currentCampusId = session('current_campus_id');
        if ($currentCampusId && !empty($validated['selectedRoles'])) {
            foreach ($validated['selectedRoles'] as $roleId) {
                \App\Models\CampusUserRole::create([
                    'user_id' => $user->id,
                    'campus_id' => $currentCampusId,
                    'role_id' => $roleId,
                ]);
            }
        }

        return redirect()->route('users')->with('success', 'User created successfully!');
    }

    public function edit(User $user)
    {
        $roleController = new \App\Http\Controllers\RoleController();
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

    public function update(Request $request, User $user)
    {
        Log::info('Update request data:', $request->all());
        $validated = $request->validate([
            'selectedRoles' => 'array',
            'selectedRoles.*' => 'exists:roles,id',
        ]);

        // Get current campus ID
        $currentCampusId = session('current_campus_id');

        if ($currentCampusId) {
            // Remove existing roles for this campus
            $user->campusRoles()->where('campus_id', $currentCampusId)->delete();

            // Add new roles for this campus
            if (!empty($validated['selectedRoles'])) {
                foreach ($validated['selectedRoles'] as $roleId) {
                    \App\Models\CampusUserRole::create([
                        'user_id' => $user->id,
                        'campus_id' => $currentCampusId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }

        return redirect()->route('users')->with('success', 'User updated successfully!');
    }


    public function destroy(User $user)
    {
        // Get current campus ID
        $currentCampusId = session('current_campus_id');

        // Remove user's roles for this campus only
        $user->campusRoles()->where('campus_id', $currentCampusId)->delete();

        // If user has no roles in any campus, delete the user
        if ($user->campusRoles()->count() === 0) {
            $user->delete();
            $message = 'User deleted successfully!';
        } else {
            $message = 'User removed from current campus successfully!';
        }

        return redirect()->route('users')->with('success', $message);
    }
}
