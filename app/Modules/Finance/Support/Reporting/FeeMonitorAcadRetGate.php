<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

/**
 * ACAD-RET-001 gate for Fee Monitor missing-fee inference on retake/resit sources.
 *
 * Reporting may show existing retake/resit charges, but must not infer missing
 * expected fees from Academic sources until ACAD-RET-001 is accepted.
 */
final class FeeMonitorAcadRetGate
{
    public static function missingInferenceEnabled(): bool
    {
        return false;
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
