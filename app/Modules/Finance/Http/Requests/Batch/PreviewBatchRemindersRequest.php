<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBatchRemindersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_operations_due_calendar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient' => 'required|in:student,parent',
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ];
    }
}