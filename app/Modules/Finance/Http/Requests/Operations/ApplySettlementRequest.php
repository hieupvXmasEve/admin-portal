<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Operations;

use App\Rules\AllocationPriorityOrder;
use Illuminate\Foundation\Http\FormRequest;

class ApplySettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority_order' => ['required', 'array', new AllocationPriorityOrder],
            'priority_order.*' => ['string'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ];
    }
}
