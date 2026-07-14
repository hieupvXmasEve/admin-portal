<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\StudentFinance;

use Illuminate\Foundation\Http\FormRequest;

final class StudentFinanceBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['semester_id' => ['nullable', 'integer', 'exists:semesters,id']];
    }

    public function semesterId(): ?int
    {
        return $this->integer('semester_id') ?: null;
    }
}
