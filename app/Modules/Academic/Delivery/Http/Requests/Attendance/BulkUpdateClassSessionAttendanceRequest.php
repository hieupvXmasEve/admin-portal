<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Attendance;

use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Actions\RecordAttendanceAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateClassSessionAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ClassSession $classSession */
        $classSession = $this->route('classSession');

        return $classSession->courseOffering->campus_id === app('campus')->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ClassSession $classSession */
        $classSession = $this->route('classSession');
        $attendanceIds = $classSession->attendances()->pluck('id');

        return [
            'attendance_ids' => ['required', 'array', 'min:1'],
            'attendance_ids.*' => ['required', 'integer', 'distinct', Rule::in($attendanceIds)],
            'status' => ['required', 'string', Rule::in(RecordAttendanceAction::STATUSES)],
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
