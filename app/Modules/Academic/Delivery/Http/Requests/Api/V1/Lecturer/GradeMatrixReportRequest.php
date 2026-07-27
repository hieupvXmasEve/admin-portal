<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Api\V1\Lecturer;

use App\Models\CourseOffering;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

final class GradeMatrixReportRequest extends LecturerAssessmentRequest
{
    public function authorize(): bool
    {
        $courseOffering = $this->route('courseOffering');

        return $courseOffering instanceof CourseOffering
            && Gate::forUser($this->user())->allows('viewGradebook', $courseOffering);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'include_excluded' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{include_excluded: bool}
     */
    public function filters(): array
    {
        return [
            'include_excluded' => $this->boolean('include_excluded', false),
        ];
    }
}
