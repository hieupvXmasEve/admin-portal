<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use App\Models\CourseOffering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional "pull latest grades from Canvas first" student
 * selection for Recalculate preview/apply (issue 11). Permission is enforced
 * by the `can:recalculate_course_offering` route middleware alone — staff do
 * not additionally need `sync_course_grades` to use the embedded pull.
 */
class RecalculateCourseOfferingRequest extends FormRequest
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

        $rosterStudentIds = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id');

        return [
            'pull_student_ids' => ['nullable', 'array', 'min:1'],
            'pull_student_ids.*' => ['required', 'integer', Rule::in($rosterStudentIds)],
        ];
    }

    public function messages(): array
    {
        return [
            'pull_student_ids.*.in' => 'The selected student is not on this course offering\'s active roster.',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
