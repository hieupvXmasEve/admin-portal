<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Attendance;

use App\Modules\Academic\Actions\Attendance\RecordAttendanceAction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a roster-batch attendance submission from the Course Offering
 * Cockpit sessions view (ADR 0013 phase B). Authorization is enforced by the
 * `can:create_course_offering` route middleware — the same ability that
 * gates the standalone AttendanceController::store endpoint.
 */
class RecordClassSessionAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.status' => ['required', 'in:'.implode(',', RecordAttendanceAction::STATUSES)],
            'records.*.notes' => ['nullable', 'string'],
        ];
    }
}
