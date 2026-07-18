<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Canvas;

use App\Models\CourseOffering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the selected students for a Canvas grade sync preview/apply
 * (issue 10). Permission is enforced by the `can:sync_course_grades` route
 * middleware; this request only checks the campus guard and that every
 * selected student is eligible to receive assessment evidence in the offering.
 */
class SyncCourseGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->route('courseOffering');

        return $courseOffering->campus_id === app('campus')->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->route('courseOffering');

        // Canvas uses Delivery's assessment-evidence eligibility (registered,
        // confirmed, and completed registrations). This deliberately differs
        // from live attendance: a completed registration may need a Canvas
        // correction until the offering itself is completed.
        $rosterStudentIds = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id');

        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', Rule::in($rosterStudentIds)],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.*.in' => 'The selected student is not eligible for Canvas assessment evidence in this course offering.',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
