<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Specialization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSpecializationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    public function rules(): array
    {
        $specialization = $this->route('specialization');
        $id = $specialization instanceof Specialization ? $specialization->id : $specialization;

        return Specialization::validationRules($id);
    }

    public function messages(): array
    {
        return Specialization::validationMessages();
    }

    protected function prepareForValidation(): void
    {
        // Convert is_active checkbox value to boolean
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => (bool) $this->input('is_active')
            ]);
        }
    }
}
