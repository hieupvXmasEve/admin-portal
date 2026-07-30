<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Dng;

use Illuminate\Foundation\Http\FormRequest;

final class ExportDngPaymentRequestsRequest extends FormRequest
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
            'status' => 'nullable|string|max:50',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'has_payment' => 'nullable|in:all,yes,no',
            'has_webhook' => 'nullable|in:all,yes,no',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
        ];
    }
}
