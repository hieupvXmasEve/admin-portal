<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface RoleAdministrationStore
{
    /** @param array{name:string, permissions?:list<int>} $data */
    public function create(array $data): int;

    /** @param array{role_id:int, name:string, permissions?:list<int>} $data */
    public function update(array $data): void;

    /** @param array{role_id:int} $data */
    public function delete(array $data): void;
}
