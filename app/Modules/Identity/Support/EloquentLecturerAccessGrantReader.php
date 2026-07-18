<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\LecturerAccessGrant as LecturerAccessGrantModel;
use App\Shared\Contracts\Identity\DTO\LecturerAccessGrant;
use App\Shared\Contracts\Identity\LecturerAccessGrantReader;

final class EloquentLecturerAccessGrantReader implements LecturerAccessGrantReader
{
    public function activeForUser(int $userId): ?LecturerAccessGrant
    {
        $grant = LecturerAccessGrantModel::query()
            ->where('user_id', $userId)
            ->where('status', LecturerAccessGrantModel::STATUS_ACTIVE)
            ->first();

        if ($grant === null) {
            return null;
        }

        return new LecturerAccessGrant(
            userId: (int) $grant->user_id,
            lecturerId: (int) $grant->lecturer_id,
            status: (string) $grant->status,
            reason: (string) $grant->reason,
        );
    }
}
