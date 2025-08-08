<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Specialization;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpecializationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    public function rules(): array
    {
        return Specialization::validationRules();
    }

    public function messages(): array
    {
        return Specialization::validationMessages();
    }

    protected function prepareForValidation(): void
    {
        // Ensure is_active is set to true by default if not provided
        if (! $this->has('is_active')) {
            $this->merge([
                'is_active' => true,
            ]);
        }
    }
}
