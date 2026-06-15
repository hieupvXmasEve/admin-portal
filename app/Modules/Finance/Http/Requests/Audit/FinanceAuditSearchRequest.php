<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;

class FinanceAuditSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_audit_workspace') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'target_type' => 'nullable|string|in:student,invoice,payment,charge,dng',
            'target_id' => 'nullable|integer|min:1',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'billing_cycle_id' => 'nullable|integer|exists:billing_cycles,id',
            'finding_code' => 'nullable|string|regex:/^INV-\\d{1,2}$/',
            'sample_id' => 'nullable|integer|min:1',
            'scope' => 'nullable|string|in:campus,all_campus',
        ];
    }
}
