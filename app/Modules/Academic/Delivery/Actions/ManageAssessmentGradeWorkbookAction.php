<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Shared\Contracts\Academic\AssessmentGradeWorkbook;
use Illuminate\Http\UploadedFile;

final class ManageAssessmentGradeWorkbookAction
{
    public static function exportTemplate(int $assessmentDetailId, int $courseOfferingId): string
    {
        return app(AssessmentGradeWorkbook::class)->exportTemplate($assessmentDetailId, $courseOfferingId);
    }

    /** @return array<string, mixed> */
    public static function import(int $assessmentDetailId, int $courseOfferingId, UploadedFile $file, array $options, int $lecturerId): array
    {
        return app(AssessmentGradeWorkbook::class)->import($assessmentDetailId, $courseOfferingId, $file, $options, $lecturerId);
    }

    public static function exportGrades(int $assessmentDetailId, int $courseOfferingId): string
    {
        return app(AssessmentGradeWorkbook::class)->exportGrades($assessmentDetailId, $courseOfferingId);
    }
}
