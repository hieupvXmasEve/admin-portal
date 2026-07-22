<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Shared\Contracts\Identity\RoleAdministrationStore;

final class CreateRoleAction
{
    /** @param array{name:string, permissions?:list<int>} $data */
    public static function run(array $data): int
    {
        return app(RoleAdministrationStore::class)->create($data);
    }
}
