<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDngPaymentFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'campus_code' => ['nullable', 'string', 'max:20'],
            'student_code' => ['nullable', 'string', 'max:50'],
            'fee_type' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:255'],
            'item_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'type' => ['nullable', 'string', 'max:20'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'estimate_time' => ['required', 'string', 'max:20'],
            'student_address' => ['nullable', 'string', 'max:500'],
            'cccd' => ['nullable', 'string', 'max:20'],
            'fee_types' => ['nullable', 'array'],
            'fee_types.*' => ['string', 'max:20'],
        ];
    }
}
