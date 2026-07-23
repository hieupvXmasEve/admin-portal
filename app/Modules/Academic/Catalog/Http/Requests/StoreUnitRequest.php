<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return UnitRequestRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UnitRequestRules::messages();
    }

    protected function prepareForValidation(): void
    {
        UnitRequestRules::normalize($this);
    }
}
