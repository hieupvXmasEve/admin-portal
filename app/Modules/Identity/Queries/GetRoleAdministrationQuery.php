<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use Illuminate\Support\Facades\DB;

final class GetRoleAdministrationQuery
{
    /** @param array{operation:'paginate'|'permissions_by_module'|'find'|'permission_ids', role_id?:int} $data */
    public function handle(array $data): mixed
    {
        return match ($data['operation']) {
            'paginate' => DB::table('roles')->orderBy('id')->paginate(),
            'permissions_by_module' => DB::table('permissions')->get()->groupBy('module'),
            'find' => DB::table('roles')->where('id', $data['role_id'])->first(),
            'permission_ids' => DB::table('role_permissions')->where('role_id', $data['role_id'])->pluck('permission_id')->map(static fn (int|string $id): int => (int) $id)->all(),
        };
    }
}
