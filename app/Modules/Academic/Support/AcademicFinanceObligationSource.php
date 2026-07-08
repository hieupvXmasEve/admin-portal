<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;

final class AcademicFinanceObligationSource
{
    public const SOURCE_SYSTEM = 'academic';

    public const COURSE_RETAKE_REGISTRATION = 'course_retake_registration';

    public const EXAM_RESIT_ATTEMPT = 'exam_resit_attempt';

    public const RETAKE_FEE = 'retake_fee';

    public const EXAM_RESIT_FEE = 'exam_resit_fee';

    public static function courseRetakeRegistrationRef(CourseRetakeRegistration|int $registration): string
    {
        $id = $registration instanceof CourseRetakeRegistration ? $registration->id : $registration;

        return "retake:{$id}";
    }

    public static function examResitAttemptRef(ExamResitAttempt|int $attempt): string
    {
        $id = $attempt instanceof ExamResitAttempt ? $attempt->id : $attempt;

        return "exam-resit:{$id}";
    }
}
