<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Policies;

use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Identity\CampusPermissionReader;

/**
 * Record-level campus authorization for the dossier lifecycle — mirrors
 * App\Policies\StudentApplicationPolicy. A staff member may act on a dossier
 * only when they hold the matching permission AT the dossier's campus, not
 * merely at their currently-selected session campus.
 */
class ScholarshipAdjustmentDossierPolicy
{
    public function __construct(private readonly CampusPermissionReader $permissionReader) {}

    public function view(User $user, ScholarshipAdjustmentDossier $dossier): bool
    {
        return $this->hasPermissionAtDossierCampus($user, $dossier, 'view_scholarship_adjustment');
    }

    public function manageInterview(User $user, ScholarshipAdjustmentDossier $dossier): bool
    {
        return $this->hasPermissionAtDossierCampus($user, $dossier, 'manage_scholarship_interview');
    }

    public function decide(User $user, ScholarshipAdjustmentDossier $dossier): bool
    {
        return $this->hasPermissionAtDossierCampus($user, $dossier, 'decide_scholarship_adjustment');
    }

    public function approve(User $user, ScholarshipAdjustmentDossier $dossier): bool
    {
        return $this->hasPermissionAtDossierCampus($user, $dossier, 'approve_scholarship_adjustment');
    }

    private function hasPermissionAtDossierCampus(User $user, ScholarshipAdjustmentDossier $dossier, string $permission): bool
    {
        $campusId = $dossier->campus_id;

        if ($campusId === null) {
            return false;
        }

        return in_array(
            $permission,
            $this->permissionReader->permissionCodesForUserId((int) $user->id, (int) $campusId),
            true,
        );
    }
}
