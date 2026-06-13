<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Operations;

use App\Modules\Finance\Enums\LifecycleDueExceptionReason;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ViewLifecycleDueExceptionHistoryRequest extends FormRequest
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
            'dng_payment_request_id' => 'nullable|integer|exists:dng_payment_requests,id',
            'dng_item_id' => 'nullable|string|max:255',
            'student_id' => 'nullable|integer|exists:students,id',
            'search' => 'nullable|string|max:255',
            'event_type' => ['nullable', 'string', Rule::enum(LifecycleDueExceptionReviewEventType::class)],
            'review_status' => ['nullable', 'string', Rule::enum(LifecycleDueExceptionReviewStatus::class)],
            'exception_reason' => ['nullable', 'string', Rule::enum(LifecycleDueExceptionReason::class)],
            'performed_by_user_id' => 'nullable|integer|exists:users,id',
            'performed_from' => 'nullable|date',
            'performed_to' => 'nullable|date|after_or_equal:performed_from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|string|in:performed_at,event_type,id',
            'direction' => 'nullable|string|in:asc,desc',
        ];
    }
}
