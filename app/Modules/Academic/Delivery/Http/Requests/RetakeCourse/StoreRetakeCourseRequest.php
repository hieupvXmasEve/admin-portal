<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\RetakeCourse;

use Illuminate\Foundation\Http\FormRequest;

class StoreRetakeCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'original_academic_record_id' => ['required', 'integer', 'exists:academic_records,id'],
            'course_offering_id' => ['nullable', 'integer', 'exists:course_offerings,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'registration_start_date' => ['nullable', 'date'],
            'registration_end_date' => ['nullable', 'date', 'after_or_equal:registration_start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Sinh viên là bắt buộc.',
            'unit_id.required' => 'Môn học là bắt buộc.',
            'original_academic_record_id.required' => 'Bản ghi học tập gốc là bắt buộc.',
            'semester_id.required' => 'Học kỳ là bắt buộc.',
            'campus_id.required' => 'Cơ sở là bắt buộc.',
        ];
    }
}
