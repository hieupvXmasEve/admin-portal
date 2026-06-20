<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class ListExamResitRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:requested,approved,rejected,cancelled,scheduled,no_show,completed'],
            'operation_state' => ['nullable', 'string', 'in:awaiting_approval,awaiting_payment,ready_to_schedule,scheduled,completed,no_show,rejected,cancelled'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'sort' => ['nullable', 'string', 'in:created_at,status,fee_amount,payment_deadline,scheduled_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
