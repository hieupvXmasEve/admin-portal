<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;

final class AcademicFinanceObligationSource
{
    public const SOURCE_SYSTEM = AcademicFinanceSourceKeys::SOURCE_SYSTEM;

    public const COURSE_RETAKE_REGISTRATION = AcademicFinanceSourceKeys::COURSE_RETAKE_REGISTRATION;

    public const EXAM_RESIT_ATTEMPT = AcademicFinanceSourceKeys::EXAM_RESIT_ATTEMPT;

    public const RETAKE_FEE = AcademicFinanceSourceKeys::RETAKE_FEE;

    public const EXAM_RESIT_FEE = AcademicFinanceSourceKeys::EXAM_RESIT_FEE;

    public static function courseRetakeRegistrationRef(CourseRetakeRegistration|int $registration): string
    {
        $id = $registration instanceof CourseRetakeRegistration ? $registration->id : $registration;

        return AcademicFinanceSourceKeys::courseRetakeRegistrationRef((int) $id);
    }

    public static function examResitAttemptRef(ExamResitAttempt|int $attempt): string
    {
        $id = $attempt instanceof ExamResitAttempt ? $attempt->id : $attempt;

        return AcademicFinanceSourceKeys::examResitAttemptRef((int) $id);
    }
}
