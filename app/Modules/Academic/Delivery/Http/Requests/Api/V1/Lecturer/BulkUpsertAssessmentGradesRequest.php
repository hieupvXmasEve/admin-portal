<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Api\V1\Lecturer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class BulkUpsertAssessmentGradesRequest extends LecturerAssessmentRequest
{
    public function authorize(): bool
    {
        return $this->canAccessBoundCourseOffering();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'grades.*.points_earned' => ['sometimes', 'numeric', 'min:0'],
            'grades.*.percentage_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'grades.*.letter_grade' => ['sometimes', 'string', 'max:5'],
            'grades.*.status' => ['sometimes', 'string', Rule::in(['not_submitted', 'submitted', 'grading', 'graded', 'returned'])],
            'grades.*.score_status' => ['sometimes', 'string', Rule::in(['draft', 'provisional', 'final'])],
            'grades.*.instructor_feedback' => ['sometimes', 'string', 'max:1000'],
            'grades.*.private_notes' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
