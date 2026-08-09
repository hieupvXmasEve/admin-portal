<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests\Student;

use App\Modules\Merchandise\Models\MerchandiseVariant;
use App\Modules\Merchandise\Models\RedemptionOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRedemptionOrderRequest extends FormRequest
{
    /** Per line, and per product (summed across variants) within one order — stops one checkout sweeping the last units. */
    public const MAX_QUANTITY_PER_LINE = 5;

    public const MAX_QUANTITY_PER_PRODUCT_PER_ORDER = 5;

    public function authorize(): bool
    {
        // Actor identity/campus/hold checks already ran in the
        // student.api.auth middleware; this endpoint has no extra permission.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'integer', 'exists:merchandise_variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY_PER_LINE],
            'method' => ['required', 'string', Rule::in(RedemptionOrder::METHODS)],
            'shipping_address' => ['required_if:method,'.RedemptionOrder::METHOD_SHIPPING, 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lines = (array) $this->input('lines', []);
            $variantIds = array_values(array_unique(array_filter(
                array_map(fn ($line) => (int) ($line['variant_id'] ?? 0), $lines)
            )));

            if ($variantIds === []) {
                return;
            }

            $merchandiseIdByVariant = MerchandiseVariant::query()
                ->whereIn('id', $variantIds)
                ->pluck('merchandise_id', 'id');

            $quantityByMerchandise = [];
            foreach ($lines as $line) {
                $variantId = (int) ($line['variant_id'] ?? 0);
                $quantity = (int) ($line['quantity'] ?? 0);
                $merchandiseId = $merchandiseIdByVariant->get($variantId);

                if ($merchandiseId === null) {
                    continue;
                }

                $quantityByMerchandise[$merchandiseId] = ($quantityByMerchandise[$merchandiseId] ?? 0) + $quantity;
            }

            foreach ($quantityByMerchandise as $total) {
                if ($total > self::MAX_QUANTITY_PER_PRODUCT_PER_ORDER) {
                    $validator->errors()->add(
                        'lines',
                        'You may redeem at most '.self::MAX_QUANTITY_PER_PRODUCT_PER_ORDER.' units of the same product per order.'
                    );

                    break;
                }
            }
        });
    }

    /**
     * @return list<array{variant_id:int, quantity:int}>
     */
    public function lines(): array
    {
        return array_map(
            fn (array $line) => ['variant_id' => (int) $line['variant_id'], 'quantity' => (int) $line['quantity']],
            (array) $this->input('lines', [])
        );
    }
}
