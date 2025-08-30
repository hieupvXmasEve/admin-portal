<?php

namespace App\Http\Controllers\Web;

use App\Constants\RoleRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use App\Services\RoleAssignmentService;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roleService,
        protected RoleAssignmentService $roleAssignmentService
    ) {}

    public function index()
    {
        return Inertia::render('roles/Index', [
            'roles' => Role::with('permissions')->paginate(),
        ]);
    }

    public function create()
    {
        return Inertia::render('roles/Add', [
            'permissions' => Permission::all()->groupBy('module'),
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $this->roleService->createRole($request->validated());

        return redirect()->route(RoleRoutes::INDEX)->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $rolePermissionIds = $role->permissions()
            ->pluck('permission_id')
            ->toArray();

        return Inertia::render('roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::all()->groupBy('module'),
            'rolePermissionIds' => $rolePermissionIds,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->roleService->updateRole($role, $request->validated());

        return redirect()->route(RoleRoutes::INDEX)->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $this->roleService->deleteRole($role);

        return redirect()->route(RoleRoutes::INDEX)->with('success', 'Role deleted successfully.');
    }

    public function getRolesWithPermissions()
    {
        return Role::with('permissions')->get();
    }
}
