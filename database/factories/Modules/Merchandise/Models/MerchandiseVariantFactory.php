<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Merchandise\Models;

use App\Models\Campus;
use App\Modules\Merchandise\Models\Merchandise;
use App\Modules\Merchandise\Models\MerchandiseVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseVariant>
 */
class MerchandiseVariantFactory extends Factory
{
    protected $model = MerchandiseVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchandise_id' => Merchandise::factory(),
            'campus_id' => Campus::factory(),
            'color' => fake()->safeColorName(),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
