<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

use App\Shared\Contracts\Identity\DTO\UserDirectoryEntry;

interface UserDirectoryReader
{
    /**
     * @param  list<int>  $excludedUserIds
     * @return list<UserDirectoryEntry>
     */
    public function excludingIds(array $excludedUserIds): array;

    /**
     * Staff accounts, name-ordered, for actor filter dropdowns.
     *
     * @return list<UserDirectoryEntry>
     */
    public function staffMembers(): array;
}
