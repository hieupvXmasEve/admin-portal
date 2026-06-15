<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class FilterFinanceChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_charges');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'student_id' => 'nullable|integer|exists:students,id',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'charge_type' => 'nullable|string',
            'status' => 'nullable|string|in:all,active,void',
            'sort' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }
}
