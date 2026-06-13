<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class ListLifecycleDueExceptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_operations_due_calendar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'lifecycle_status' => 'nullable|string|in:deferred,dropout,dropout_transfer,inactive_or_non_financial,missing_student',
            'review_status' => 'nullable|string|in:open,acknowledged,kept_as_debt,routed_to_settlement,cancel_requested,resolved,ignored,all',
            'due_status' => 'nullable|string|in:upcoming,due_today,overdue,all',
            'fee_type' => 'nullable|string|max:32',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|string|in:due_date,amount,id,created_at',
            'direction' => 'nullable|string|in:asc,desc',
        ];
    }
}
