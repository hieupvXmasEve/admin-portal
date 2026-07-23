<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
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
        $unit = $this->route('unit');

        return UnitRequestRules::rules($unit instanceof Unit ? $unit->id : null);
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
