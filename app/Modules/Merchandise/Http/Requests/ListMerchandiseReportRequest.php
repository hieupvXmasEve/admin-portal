<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests;

use App\Models\RedemptionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMerchandiseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_merchandise_report') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', Rule::in(RedemptionOrder::STATUSES)],
        ];
    }
}
