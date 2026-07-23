<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admission_date' => ['nullable', 'date'],
            'expected_graduation_date' => ['nullable', 'date', 'after:admission_date'],
        ];
    }
}
