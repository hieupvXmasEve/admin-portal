<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBatchChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create_finance_charges')
            || $this->user()?->can('generate_egc_finance_charges'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fee_category' => 'required|in:major,egc,non_academic',
            'semester_id' => 'required|integer|exists:semesters,id',
            'scope' => 'nullable|array',
            'scope.filters' => 'nullable|array',
            'scope.fee_type' => 'nullable|string',
            'scope.amount' => 'nullable|numeric|min:1',
            'scope.due_date' => 'nullable|date',
        ];
    }
}