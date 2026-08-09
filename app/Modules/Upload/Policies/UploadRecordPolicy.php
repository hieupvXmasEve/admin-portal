<?php

declare(strict_types=1);

namespace App\Modules\Upload\Policies;

use App\Models\User;
use App\Modules\Upload\Models\UploadRecord;
use App\Modules\Upload\Support\UploadPlatform;

class UploadRecordPolicy
{
    public function __construct(private readonly UploadPlatform $uploads) {}

    public function view(User $user, UploadRecord $uploadRecord): bool
    {
        if ($uploadRecord->context === 'branding') {
            return false;
        }

        return $this->isAdministrator($user)
            || $uploadRecord->user_id === $user->id
            || $this->uploads->isContextPublic($uploadRecord->context);
    }

    public function delete(User $user, UploadRecord $uploadRecord): bool
    {
        if ($uploadRecord->context === 'branding') {
            return false;
        }

        return $this->isAdministrator($user) || $uploadRecord->user_id === $user->id;
    }

    private function isAdministrator(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
