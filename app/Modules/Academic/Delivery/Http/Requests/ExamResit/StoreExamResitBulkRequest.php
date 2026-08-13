<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamResitBulkRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'items.*.academic_record_id' => ['required', 'integer', 'exists:academic_records,id'],
            'items.*.campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'operation_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'charge_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Vui lòng chọn ít nhất một bản ghi để đăng ký thi lại.',
            'items.min' => 'Vui lòng chọn ít nhất một bản ghi để đăng ký thi lại.',
            'items.max' => 'Chỉ có thể đăng ký tối đa 200 bản ghi trong một lần.',
        ];
    }
}
