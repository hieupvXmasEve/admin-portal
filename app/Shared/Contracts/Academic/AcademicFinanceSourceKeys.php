<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

final class AcademicFinanceSourceKeys
{
    public const SOURCE_SYSTEM = 'academic';

    public const COURSE_RETAKE_REGISTRATION = 'course_retake_registration';

    public const EXAM_RESIT_ATTEMPT = 'exam_resit_attempt';

    public const RETAKE_FEE = 'retake_fee';

    public const EXAM_RESIT_FEE = 'exam_resit_fee';

    public const EGC_BLOCK = 'egc_block';

    public static function courseRetakeRegistrationRef(int $registrationId): string
    {
        return "retake:{$registrationId}";
    }

    public static function examResitAttemptRef(int $attemptId): string
    {
        return "exam-resit:{$attemptId}";
    }

    public static function egcBlockRef(int $blockId): string
    {
        return "egc-block:{$blockId}";
    }

    public static function sourceIdFromRef(string $sourceRef, string $prefix): ?int
    {
        if (! str_starts_with($sourceRef, $prefix)) {
            return null;
        }

        $id = (int) substr($sourceRef, strlen($prefix));

        return $id > 0 ? $id : null;
    }
}
