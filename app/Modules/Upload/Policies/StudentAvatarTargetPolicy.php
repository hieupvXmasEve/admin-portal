<?php

declare(strict_types=1);

namespace App\Modules\Upload\Policies;

use App\Models\User;
use App\Modules\Upload\Support\StudentAvatarTarget;

class StudentAvatarTargetPolicy
{
    public function upload(User $user, StudentAvatarTarget $target): bool
    {
        return $user->can('edit_student');
    }
}
