<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class FilterPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_payments');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'source' => 'nullable|string',
            'status' => 'nullable|string',
            'date_range' => 'nullable|array|size:2',
            'date_range.*' => 'nullable|date',
            'sort' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
