<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Reporting;

use App\Modules\Finance\Support\Reporting\FeeMonitorExpectedFeeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFeeMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_reporting') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $expectedSources = array_keys(FeeMonitorExpectedFeeCatalog::sources());

        return [
            'view' => 'nullable|string|max:64',
            'program_id' => 'nullable',
            'intake_semester_id' => 'nullable',
            'cohort' => 'nullable',
            'expected_fee_type' => ['nullable', Rule::in(array_merge(['all'], $expectedSources))],
            'generation_state' => 'nullable|string|in:missing,generated,skipped,voided,blocked,all',
            'payment_state' => 'nullable|string|in:paid,partially_paid,outstanding,invalid,all',
            'student_status' => 'nullable|string|max:64',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|in:20,50,100',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
