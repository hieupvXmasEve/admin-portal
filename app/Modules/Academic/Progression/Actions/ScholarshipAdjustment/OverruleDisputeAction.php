<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Identity\CampusPermissionReader;

/**
 * An approver reviews a student's dispute and lets the adjustment proceed
 * anyway.
 *
 * This exists so a dispute is never resolved by pretending the student agreed.
 * Confirming on behalf is for students who did not respond; a student who DID
 * respond, in disagreement, can only be overruled — explicitly, by someone
 * holding the approver permission, with a written reason, and with their own
 * words (`student_comment`) left intact.
 *
 * The normal resolution is still to correct the minutes and ask again
 * (EditMinutesAction); this is the escape hatch for a dispute that survives
 * that, so a fee decision cannot be blocked indefinitely.
 *
 * Single writer for the dispute_overrule_* columns.
 */
class OverruleDisputeAction
{
    public function __construct(private readonly CampusPermissionReader $permissions) {}

    public function run(
        ScholarshipAdjustmentDossier $dossier,
        int $approverUserId,
        string $reason,
    ): ScholarshipAdjustmentDossier {
        if ($dossier->confirmation_status !== ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED) {
            throw new \DomainException(
                'Không có gì để bác bỏ — sinh viên không phản đối biên bản phỏng vấn.',
            );
        }

        // Campus-scoped: the approver permission must be held AT the dossier's
        // campus, not merely at the actor's currently-selected campus.
        $codes = $this->permissions->permissionCodesForUserId($approverUserId, (int) $dossier->campus_id);

        if (! in_array('approve_scholarship_adjustment', $codes, true)) {
            throw new \DomainException('Chỉ người có quyền duyệt quyết định học bổng tại cơ sở này mới được bác bỏ phản đối.');
        }

        $dossier->update([
            'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTE_OVERRULED,
            'dispute_overruled_at' => now(),
            'dispute_overruled_by_user_id' => $approverUserId,
            'dispute_overrule_reason' => $reason,
            // student_comment, confirmed_at and confirmed_by_user_id are left
            // untouched on purpose: the student's objection stands on the
            // record, and no confirmation is fabricated.
        ]);

        return $dossier->refresh();
    }
}
