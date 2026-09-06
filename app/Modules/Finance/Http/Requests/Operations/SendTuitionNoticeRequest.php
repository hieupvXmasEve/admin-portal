<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class SendTuitionNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_operations_due_calendar') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'Vui lòng chọn ít nhất một sinh viên.',
            'student_ids.min' => 'Vui lòng chọn ít nhất một sinh viên.',
        ];
    }
}
