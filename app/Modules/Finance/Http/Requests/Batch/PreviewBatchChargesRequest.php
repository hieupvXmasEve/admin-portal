<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewBatchChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ((string) $this->input('fee_category')) {
            'egc' => (bool) $this->user()?->can('generate_egc_finance_charges'),
            'major', 'non_academic' => (bool) $this->user()?->can('create_finance_charges'),
            default => false,
        };
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
            'scope.fee_type' => [
                'exclude_unless:fee_category,non_academic',
                'required',
                'string',
                Rule::enum(NonAcademicChargeTypeEnum::class),
            ],
            'scope.amount' => 'exclude_unless:fee_category,non_academic|required|numeric|min:1',
            'scope.note' => 'exclude_unless:fee_category,non_academic|nullable|string|max:255',
        ];
    }
}
