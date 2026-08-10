<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Policies;

use App\Models\StudentApplication;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Contracts\Auth\Authenticatable;

final class StudentApplicationPolicy
{
    public function __construct(private readonly CampusPermissionReader $permissionReader) {}

    public function approve(Authenticatable $user, StudentApplication $application): bool
    {
        // Deliberately no null-campus fallback: approval must stay blocked
        // until a real campus is mapped (Phase 4/5 readiness gate).
        return $this->hasPermissionAtApplicationCampus($user, $application, 'approve_student_application');
    }

    public function reject(Authenticatable $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtSessionFallbackCampus($user, $application, 'reject_student_application');
    }

    public function revoke(Authenticatable $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtSessionFallbackCampus($user, $application, 'revoke_student_application');
    }

    private function hasPermissionAtApplicationCampus(Authenticatable $user, StudentApplication $application, string $permission): bool
    {
        $campusId = $application->campus?->id;

        return $campusId !== null && in_array($permission, $this->permissionReader->permissionCodesForUserId((int) $user->getAuthIdentifier(), $campusId), true);
    }

    /**
     * A null-campus CRM record (unmapped) has no application campus to check
     * against, so reject/revoke fall back to the actor's *current session*
     * campus permissions instead — keeping an unmapped record removable.
     */
    private function hasPermissionAtSessionFallbackCampus(Authenticatable $user, StudentApplication $application, string $permission): bool
    {
        $campusId = $application->campus?->id ?? session('current_campus_id');

        return $campusId !== null && in_array($permission, $this->permissionReader->permissionCodesForUserId((int) $user->getAuthIdentifier(), $campusId), true);
    }
}
