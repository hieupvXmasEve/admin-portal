<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Web\Admin;

use App\Constants\RoleRoutes;
use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\CreateRoleAction;
use App\Modules\Identity\Actions\DeleteRoleAction;
use App\Modules\Identity\Actions\UpdateRoleAction;
use App\Modules\Identity\Http\Requests\Identity\StoreRoleRequest;
use App\Modules\Identity\Http\Requests\Identity\UpdateRoleRequest;
use App\Modules\Identity\Queries\GetRoleAdministrationQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function __construct(private readonly GetRoleAdministrationQuery $roles) {}

    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => $this->roles->handle(['operation' => 'paginate']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Add', [
            'permissions' => $this->roles->handle(['operation' => 'permissions_by_module']),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        CreateRoleAction::run($request->validated());
        Inertia::flash('success', 'Role created successfully.');

        return redirect()->route(RoleRoutes::INDEX);
    }

    public function show(int $role): Response
    {
        $roleRecord = $this->roles->handle(['operation' => 'find', 'role_id' => $role]) ?? abort(404);

        return Inertia::render('Roles/Edit', [
            'role' => $roleRecord,
            'permissions' => $this->roles->handle(['operation' => 'permissions_by_module']),
            'rolePermissionIds' => $this->roles->handle(['operation' => 'permission_ids', 'role_id' => (int) $roleRecord->id]),
        ]);
    }

    public function edit(int $role): Response
    {
        $roleRecord = $this->roles->handle(['operation' => 'find', 'role_id' => $role]) ?? abort(404);

        return Inertia::render('Roles/Edit', [
            'role' => $roleRecord,
            'permissions' => $this->roles->handle(['operation' => 'permissions_by_module']),
            'rolePermissionIds' => $this->roles->handle(['operation' => 'permission_ids', 'role_id' => (int) $roleRecord->id]),
        ]);
    }

    public function update(UpdateRoleRequest $request, int $role): RedirectResponse
    {
        UpdateRoleAction::run([...$request->validated(), 'role_id' => $role]);
        Inertia::flash('success', 'Role updated successfully.');

        return redirect()->route(RoleRoutes::INDEX);
    }

    public function destroy(int $role): RedirectResponse
    {
        DeleteRoleAction::run(['role_id' => $role]);
        Inertia::flash('success', 'Role deleted successfully.');

        return redirect()->route(RoleRoutes::INDEX);
    }
}
