<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MarkAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'attendance_data' => [
                'required',
                'array',
                'min:1',
                'max:200' // Reasonable limit for class size
            ],
            'attendance_data.*.student_code' => [
                'required',
                'integer',
                'exists:students,id'
            ],
            'attendance_data.*.status' => [
                'required',
                'string',
                'in:present,absent,late,excused'
            ],
            'attendance_data.*.check_in_time' => [
                'nullable',
                'date_format:H:i:s'
            ],
            'attendance_data.*.minutes_late' => [
                'nullable',
                'integer',
                'min:0',
                'max:180' // Max 3 hours late
            ],
            'attendance_data.*.participation_score' => [
                'nullable',
                'numeric',
                'min:0',
                'max:10'
            ],
            'attendance_data.*.notes' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'attendance_data.required' => 'Attendance data is required',
            'attendance_data.array' => 'Attendance data must be an array',
            'attendance_data.min' => 'At least one attendance record is required',
            'attendance_data.max' => 'Cannot process more than 200 attendance records at once',
            'attendance_data.*.student_code.required' => 'Student ID is required for each attendance record',
            'attendance_data.*.student_code.exists' => 'One or more student IDs do not exist',
            'attendance_data.*.status.required' => 'Attendance status is required for each record',
            'attendance_data.*.status.in' => 'Attendance status must be: present, absent, late, or excused',
            'attendance_data.*.check_in_time.date_format' => 'Check-in time must be in HH:MM:SS format',
            'attendance_data.*.minutes_late.integer' => 'Minutes late must be a valid number',
            'attendance_data.*.minutes_late.min' => 'Minutes late cannot be negative',
            'attendance_data.*.minutes_late.max' => 'Minutes late cannot exceed 180 minutes',
            'attendance_data.*.participation_score.numeric' => 'Participation score must be a number',
            'attendance_data.*.participation_score.min' => 'Participation score cannot be negative',
            'attendance_data.*.participation_score.max' => 'Participation score cannot exceed 10',
            'attendance_data.*.notes.max' => 'Notes cannot exceed 500 characters'
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Attendance marking validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values and clean up data
        if ($this->has('attendance_data')) {
            $attendanceData = collect($this->attendance_data)->map(function ($record) {
                // Set minutes_late to 0 if status is not 'late'
                if (isset($record['status']) && $record['status'] !== 'late') {
                    $record['minutes_late'] = 0;
                }

                // Remove check_in_time if status is 'absent'
                if (isset($record['status']) && $record['status'] === 'absent') {
                    unset($record['check_in_time']);
                }

                return $record;
            })->toArray();

            $this->merge(['attendance_data' => $attendanceData]);
        }
    }
}
