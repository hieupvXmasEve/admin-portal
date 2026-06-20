<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class CompleteExamResitRequest extends FormRequest
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
            'resit_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'resit_grade' => ['nullable', 'string', 'max:10'],
            'sat_at' => ['nullable', 'date'],
            'unpaid_sitting_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
