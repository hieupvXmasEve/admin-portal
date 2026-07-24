<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use Illuminate\Http\UploadedFile;

interface AssessmentGradeWorkbook
{
    public function exportTemplate(int $assessmentDetailId, int $courseOfferingId): string;

    /** @return array<string, mixed> */
    public function import(int $assessmentDetailId, int $courseOfferingId, UploadedFile $file, array $options, int $lecturerId): array;

    public function exportGrades(int $assessmentDetailId, int $courseOfferingId): string;
}
