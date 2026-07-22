<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\Role;
use App\Shared\Contracts\Identity\RoleAdministrationStore;
use Illuminate\Support\Facades\DB;

final class EloquentRoleAdministrationStore implements RoleAdministrationStore
{
    public function create(array $data): int
    {
        return Role::query()->getConnection()->transaction(function () use ($data): int {
            $permissions = $data['permissions'] ?? [];
            unset($data['permissions']);
            $role = Role::query()->create($data);
            $role->permissions()->sync($permissions);

            return (int) $role->id;
        });
    }

    public function update(array $data): void
    {
        Role::query()->getConnection()->transaction(function () use ($data): void {
            $permissions = $data['permissions'] ?? [];
            $role = Role::query()->findOrFail($data['role_id']);
            $role->update(['name' => $data['name']]);
            $role->permissions()->sync($permissions);
        });
    }

    public function delete(array $data): void
    {
        Role::query()->getConnection()->transaction(function () use ($data): void {
            $role = Role::query()->findOrFail($data['role_id']);
            DB::table('campus_user_roles')->where('role_id', $role->id)->delete();
            $role->permissions()->detach();
            $role->delete();
        });
    }
}
