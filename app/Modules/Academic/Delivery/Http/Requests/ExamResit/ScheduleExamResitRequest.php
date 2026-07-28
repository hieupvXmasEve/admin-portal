<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleExamResitRequest extends FormRequest
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
            'exam_resit_session_id' => ['required', 'integer', 'exists:exam_resit_sessions,id'],
            'unpaid_sitting_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
