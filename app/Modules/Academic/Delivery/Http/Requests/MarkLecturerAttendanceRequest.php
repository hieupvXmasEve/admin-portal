<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MarkLecturerAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendance_data' => ['required', 'array', 'min:1', 'max:200'],
            'attendance_data.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'attendance_data.*.status' => ['required', 'in:present,absent,late,excused'],
            'attendance_data.*.check_in_time' => ['nullable', 'date_format:H:i:s'],
            'attendance_data.*.minutes_late' => ['nullable', 'integer', 'min:0', 'max:180'],
            'attendance_data.*.participation_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'attendance_data.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('attendance_data')) {
            return;
        }

        $attendanceData = collect($this->input('attendance_data'))->map(static function (array $record): array {
            if (($record['status'] ?? null) !== 'late') {
                $record['minutes_late'] = 0;
            }

            if (($record['status'] ?? null) === 'absent') {
                unset($record['check_in_time']);
            }

            return $record;
        })->all();

        $this->merge(['attendance_data' => $attendanceData]);
    }
}
