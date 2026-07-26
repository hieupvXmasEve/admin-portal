<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Shared\Contracts\Identity\DTO\UserDirectoryEntry;
use App\Shared\Contracts\Identity\UserDirectoryReader;

final class EloquentUserDirectoryReader implements UserDirectoryReader
{
    public function excludingIds(array $excludedUserIds): array
    {
        return User::query()
            ->whereNotIn('id', $excludedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): UserDirectoryEntry => new UserDirectoryEntry(
                id: (int) $user->id,
                name: (string) $user->name,
                email: (string) $user->email,
            ))
            ->all();
    }
}
