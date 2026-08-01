<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustMerchandiseVariantStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjust_merchandise_stock');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'change' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', 'string', Rule::in(StockMovement::TYPES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
