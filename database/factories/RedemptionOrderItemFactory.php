<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MerchandiseVariant;
use App\Models\RedemptionOrder;
use App\Models\RedemptionOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedemptionOrderItem>
 */
class RedemptionOrderItemFactory extends Factory
{
    protected $model = RedemptionOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $priceEach = fake()->numberBetween(5, 100);

        return [
            'redemption_order_id' => RedemptionOrder::factory(),
            'merchandise_variant_id' => MerchandiseVariant::factory(),
            'merchandise_name' => fake()->words(3, true),
            'variant_label' => fake()->safeColorName().' / '.fake()->randomElement(['S', 'M', 'L', 'XL']),
            'gold_price_each' => $priceEach,
            'line_total' => $priceEach * $quantity,
            'quantity' => $quantity,
        ];
    }
}
