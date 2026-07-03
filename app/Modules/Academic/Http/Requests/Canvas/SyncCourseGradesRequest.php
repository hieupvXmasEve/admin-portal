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
 * selected student is on the offering's active roster.
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

        // Matches the roster CanvasGradeSyncService actually syncs (registered,
        // confirmed, completed) — broader than the "active class roster" used
        // for attendance, since a completed registration can still get a
        // late Canvas correction while the offering itself isn't completed yet.
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
            'student_ids.*.in' => 'The selected student is not on this course offering\'s active roster.',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
