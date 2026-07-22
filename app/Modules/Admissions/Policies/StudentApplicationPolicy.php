<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Policies;

use App\Models\StudentApplication;
use App\Services\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;

final class StudentApplicationPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function approve(Authenticatable $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtApplicationCampus($user, $application, 'approve_student_application');
    }

    public function reject(Authenticatable $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtApplicationCampus($user, $application, 'reject_student_application');
    }

    public function revoke(Authenticatable $user, StudentApplication $application): bool
    {
        return $this->hasPermissionAtApplicationCampus($user, $application, 'revoke_student_application');
    }

    private function hasPermissionAtApplicationCampus(Authenticatable $user, StudentApplication $application, string $permission): bool
    {
        $campusId = $application->campus?->id;

        return $campusId !== null && in_array($permission, $this->permissionService->getUserPermissions($user, $campusId), true);
    }
}
