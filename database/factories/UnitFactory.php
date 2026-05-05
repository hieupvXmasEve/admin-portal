<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = ['CS', 'BUS', 'GC', 'VV', 'MC', 'MATH', 'ENG', 'SCI'];
        $levels = [100, 200, 300, 400];
        $department = fake()->randomElement($departments);
        $level = fake()->randomElement($levels);
        $number = fake()->numberBetween(1, 99);

        return [
            'code' => sprintf('%s%d%02d', $department, $level, $number),
            'name' => fake()->sentence(3, true),
            'credit_points' => fake()->randomElement([2.0, 3.0, 4.0, 6.0]),
            'retake_fee' => fake()->randomElement([3000000, 5000000, 7500000]),
        ];
    }
}
