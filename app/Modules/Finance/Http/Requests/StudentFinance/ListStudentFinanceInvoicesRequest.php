<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\StudentFinance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListStudentFinanceInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<Rule|string>> */
    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'pending', 'paid', 'overdue', 'cancelled', 'open', 'zero_amount', 'issued', 'invalid'])],
        ];
    }

    public function semesterId(): ?int
    {
        return $this->integer('semester_id') ?: null;
    }
}
