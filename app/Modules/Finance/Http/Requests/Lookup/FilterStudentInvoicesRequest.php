<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class FilterStudentInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_invoices');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'status' => 'nullable|string',
            'sort' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
