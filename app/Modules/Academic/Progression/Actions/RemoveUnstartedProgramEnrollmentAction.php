<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use RuntimeException;

final class RemoveUnstartedProgramEnrollmentAction
{
    /** @param array{student_id: int} $data */
    public static function run(array $data): void
    {
        $enrollment = ProgramEnrollment::query()
            ->where('student_id', $data['student_id'])
            ->where('source_type', ProgramEnrollment::LEGACY_STUDENT_SOURCE)
            ->where('source_id', $data['student_id'])
            ->lockForUpdate()
            ->first();

        if ($enrollment === null) {
            return;
        }

        if ($enrollment->enrollment_status !== 'active' || $enrollment->study_stage !== null) {
            throw new RuntimeException(
                'This student already has academic or financial activity and cannot be revoked. '
                .'Use the Withdraw process to remove a student who has already studied.'
            );
        }

        $enrollment->delete();
    }
}
