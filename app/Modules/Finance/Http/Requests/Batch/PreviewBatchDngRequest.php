<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use Illuminate\Foundation\Http\FormRequest;

class PreviewBatchDngRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create_finance_payments');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semester_id' => 'required|integer|exists:semesters,id',
            'dng_fee_type' => 'required|string|in:'.implode(',', DngFeeTypeOptions::values()),
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer',
        ];
    }
}