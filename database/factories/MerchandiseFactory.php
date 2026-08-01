<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Merchandise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchandise>
 */
class MerchandiseFactory extends Factory
{
    protected $model = Merchandise::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'gold_price' => fake()->numberBetween(50, 2000),
            'status' => Merchandise::STATUS_ACTIVE,
        ];
    }
}
