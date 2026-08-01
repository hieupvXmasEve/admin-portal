<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMerchandiseVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_merchandise_variant');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'color' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:merchandise_variants,sku'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
