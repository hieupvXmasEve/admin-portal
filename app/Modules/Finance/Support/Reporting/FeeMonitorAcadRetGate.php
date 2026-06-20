<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

/**
 * ACAD-RET-001 gate for Fee Monitor missing-fee inference on retake/resit sources.
 *
 * Enabled 2026-06-20 (ACAD-RET-001 slice 8): the Academic source contract is in
 * place (approved CourseRetakeRegistration / ExamResitAttempt) and legacy
 * exam_resit_fee charges have been reconciled, so the Fee Monitor may now infer a
 * missing retake/resit fee from an approved Academic source that has no charge yet.
 */
final class FeeMonitorAcadRetGate
{
    public static function missingInferenceEnabled(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function excludedMissingSources(): array
    {
        if (self::missingInferenceEnabled()) {
            return [];
        }

        return ['course_retake', 'exam_resit'];
    }
}
