<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Attendance;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\RecordAttendanceAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a roster-batch attendance submission from the Course Offering
 * Cockpit sessions view (ADR 0013 phase B). Permission is enforced by the
 * `can:create_course_offering` route middleware — the same ability that
 * gates the standalone AttendanceController::store endpoint. `authorize()`
 * only checks that the route's session/offering/campus actually line up
 * (mirrors CourseOfferingController::show's campus guard), matching the
 * "controller stays thin" convention rather than checking this in the
 * controller.
 */
class RecordClassSessionAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->route('courseOffering');
        /** @var ClassSession $classSession */
        $classSession = $this->route('classSession');

        return $courseOffering->campus_id === app('campus')->id
            && $classSession->course_offering_id === $courseOffering->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->route('courseOffering');

        // Only students on this offering's active class roster (the same
        // relation the lecturer attendance flow uses to decide who can be
        // marked) are valid attendance-recording targets.
        $rosterStudentIds = $courseOffering->activeClassRosterRegistrations()->pluck('student_id');

        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', Rule::in($rosterStudentIds)],
            'records.*.status' => ['required', 'in:'.implode(',', RecordAttendanceAction::STATUSES)],
            'records.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'records.*.student_id.in' => 'The selected student is not on this course offering\'s active roster.',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
