<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Request $request, Role $role)
    {
        // Validate input
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;

        $query = $role->newQuery()->orderBy('id', 'desc');

        $roles = $query->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('roles/Index', [
            'roles' => Inertia::deepMerge($roles),
        ]);
    }

    public function getRolesWithPermissions()
    {
        $roles = Role::with(['permissions' => function ($query) {
            $query->with('children')->orderBy('parent_id')->orderBy('name');
        }])->orderBy('name')->get();

        // Group permissions by parent_id for better organization
        $rolesWithGroupedPermissions = $roles->map(function ($role) {
            $permissions = $role->permissions;

            // Separate parent permissions and child permissions
            $parentPermissions = $permissions->whereNull('parent_id');
            $childPermissions = $permissions->whereNotNull('parent_id');

            // Group child permissions by parent_id
            $groupedPermissions = $parentPermissions->map(function ($parent) use ($childPermissions) {
                return [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'code' => $parent->code,
                    'description' => $parent->description,
                    'parent_id' => $parent->parent_id,
                    'children' => $childPermissions->where('parent_id', $parent->id)->values()
                ];
            });

            // Add orphaned child permissions (those whose parent is not in this role)
            $orphanedChildren = $childPermissions->filter(function ($child) use ($parentPermissions) {
                return !$parentPermissions->contains('id', $child->parent_id);
            });

            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $groupedPermissions->concat($orphanedChildren)->values()
            ];
        });

        return $rolesWithGroupedPermissions;
    }

    public function create()
    {
        $permissions = \App\Models\Permission::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('roles/Add', [
            'permissions' => $permissions
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'exists:permissions,id',
        ]);

        // Create the role
        $role = Role::create([
            'name' => $validated['name'],
        ]);

        // Assign permissions to the role
        if (!empty($validated['selectedPermissions'])) {
            $role->permissions()->attach($validated['selectedPermissions']);
        }

        return redirect()->route('roles.index')->with('success', 'Role created successfully!');
    }

    public function edit(Role $role)
    {
        $permissions = \App\Models\Permission::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        // Get role's current permissions
        $rolePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        return Inertia::render('roles/Edit', [
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissionIds' => $rolePermissionIds
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'exists:permissions,id',
        ]);

        // Update the role
        $role->update([
            'name' => $validated['name'],
        ]);

        // Sync permissions
        $role->permissions()->sync($validated['selectedPermissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Role updated successfully!');
    }
}
