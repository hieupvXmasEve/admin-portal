<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamResitRequest extends FormRequest
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
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'academic_record_id' => ['required', 'integer', 'exists:academic_records,id'],
            'operation_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'charge_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
