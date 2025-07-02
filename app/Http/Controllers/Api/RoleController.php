<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\Role\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roleService) {}

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::with('permissions')->paginate());
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $role = $this->roleService->createRole($request->validated());
        return new RoleResource($role);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $updatedRole = $this->roleService->updateRole($role, $request->validated());
        return new RoleResource($updatedRole);
    }

    public function destroy(Role $role): \Illuminate\Http\Response
    {
        $this->roleService->deleteRole($role);
        return response()->noContent();
    }
}
