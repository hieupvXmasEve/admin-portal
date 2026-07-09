<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

class ListPricingRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_pricing_operations') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'obligation_type' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
