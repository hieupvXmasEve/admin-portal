<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;

class GetRevenueReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_revenue_report') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semester_ids' => 'nullable|array',
            'semester_ids.*' => 'integer|exists:semesters,id',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'fee_type' => 'nullable|string|max:64',
        ];
    }
}
