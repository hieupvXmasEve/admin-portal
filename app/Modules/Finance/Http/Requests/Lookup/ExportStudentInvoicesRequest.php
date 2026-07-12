<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

final class ExportStudentInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_export_invoices');
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
            'as_of' => 'nullable|date|max:64',
            'as_of_timezone' => 'nullable|timezone',
        ];
    }
}
