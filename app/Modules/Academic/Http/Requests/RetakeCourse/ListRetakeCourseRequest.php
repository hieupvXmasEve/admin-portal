<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\RetakeCourse;

use Illuminate\Foundation\Http\FormRequest;

class ListRetakeCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:approved,payment_pending,paid,enrolled,cancelled'],
            'operation_state' => ['nullable', 'string', 'in:awaiting_payment,paid_waiting_class,enrolled,needs_review,cancelled'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'sort' => ['nullable', 'string', 'in:created_at,status,retake_fee,payment_deadline'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
