<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * campus_id is intentionally not editable here (ponytail: moving a variant
 * cross-campus would need to re-authorize against BOTH the old and new
 * campus and is not needed yet — create a new variant at the target campus
 * instead. Add cross-campus move support if that operational need shows up).
 */
class UpdateMerchandiseVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_merchandise_variant');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $variant = $this->route('variant');

        return [
            'color' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('merchandise_variants', 'sku')->ignore($variant?->id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
