<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Reporting;

use App\Modules\Finance\Support\Reporting\CollectionProgressCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCollectionProgressRequest extends FormRequest
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
        return [
            'view' => 'nullable|string|max:64',
            'program_id' => 'nullable',
            'intake_semester_id' => 'nullable',
            'cohort' => 'nullable',
            'fee_type' => 'nullable|string|max:64',
            'balance_state' => ['nullable', 'string', Rule::in(array_merge(['all'], CollectionProgressCatalog::balanceStateKeys()))],
            'aging_bucket' => ['nullable', 'string', Rule::in(array_merge(['all'], CollectionProgressCatalog::agingBucketKeys()))],
            'student_status' => 'nullable|string|max:64',
            'search' => 'nullable|string|max:255',
            'as_of' => 'nullable|date|max:64',
            'as_of_timezone' => 'nullable|timezone',
            'per_page' => 'nullable|integer|in:20,50,100',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
