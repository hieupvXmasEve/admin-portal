<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Api\V1\Lecturer;

final class ExportAssessmentGradesRequest extends LecturerAssessmentRequest
{
    public function authorize(): bool
    {
        return $this->canAccessBoundCourseOffering();
    }

    public function rules(): array
    {
        return [];
    }

    public function format(): string
    {
        $format = $this->query('format', 'excel');

        return is_string($format) ? $format : '';
    }
}
