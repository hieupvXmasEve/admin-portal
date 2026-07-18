<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lecture;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use Illuminate\Http\Request;

class ApiActorPolicy
{
    public function __construct(
        private readonly GuardianAccessGrantReader $guardianAccessGrantReader,
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

        if ($user->trashed()) {
            return false;
        }

        return (bool) $user->is_active
            && in_array((string) $user->employment_status, ['active', 'employed', 'contract_active'], true);
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
