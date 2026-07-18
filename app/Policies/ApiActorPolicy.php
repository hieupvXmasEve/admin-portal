<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lecture;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\DTO\LecturerAccessGrant;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Identity\LecturerAccessGrantReader;
use Illuminate\Http\Request;

class ApiActorPolicy
{
    public function __construct(
        private readonly GuardianAccessGrantReader $guardianAccessGrantReader,
        private readonly LecturerAccessGrantReader $lecturerAccessGrantReader,
    ) {}

    public function accessStudentOrParent(mixed $user, Request $request): bool
    {
        if ($user instanceof Student) {
            return $user->isActive();
        }

        return $this->isActiveParent($user);
    }

    public function accessParent(mixed $user, Request $request): bool
    {
        return $this->isActiveParent($user);
    }

    public function accessLecturer(mixed $user, Request $request): bool
    {
        if (! $user instanceof Lecture) {
            return false;
        }

        $account = User::query()->find($user->user_id);

        return $account !== null && $this->lecturerAccessFor($account) !== null;
    }

    public function lecturerAccessFor(User $user): ?LecturerAccessGrant
    {
        if (! $user->isActive() || ! $user->isLecturer()) {
            return null;
        }

        return $this->lecturerAccessGrantReader->activeForUser((int) $user->id);
    }

    private function isActiveParent(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->isActive() || ! $user->isParent()) {
            return false;
        }

        return $user->parentProfile?->status === 'active'
            && $this->guardianAccessGrantReader->hasAnyActiveGrant((int) $user->id);
    }
}
