<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StudentApplication;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Campus-scoped authorization for the Application lifecycle.
 *
 * A staff member may approve/reject/revoke an Application only when they hold
 * the matching permission *at the Application's campus* — not merely at their
 * currently-selected campus. This mirrors the platform's Campus scoping, which
 * resolves a user's permissions per campus through {@see PermissionService}.
 */
class StudentApplicationPolicy
{
    public function __construct(private PermissionService $permissionService) {}

    public function approve(User $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtApplicationCampus($user, $application, 'approve_student_application');
    }

    public function reject(User $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtApplicationCampus($user, $application, 'reject_student_application');
    }

    public function revoke(User $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtApplicationCampus($user, $application, 'revoke_student_application');
    }

    /**
     * Resolve the Application's campus, then check the user's permissions scoped
     * to that campus. An Application whose campus cannot be resolved is denied.
     */
    private function hasPermissionAtApplicationCampus(
        User $user,
        StudentApplication $application,
        string $permission
    ): bool {
        $campusId = $application->campus?->id;

        if ($campusId === null) {
            return false;
        }

        return in_array(
            $permission,
            $this->permissionService->getUserPermissions($user, $campusId),
            true
        );
    }
}
