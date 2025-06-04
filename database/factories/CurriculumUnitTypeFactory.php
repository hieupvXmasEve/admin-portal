<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurriculumUnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurriculumUnitType>
 */
class CurriculumUnitTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CurriculumUnitType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['core', 'elective', 'major']),
        ];
    }

    /**
     * Create a core unit type.
     */
    public function core(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'core',
        ]);
    }

    /**
     * Create an elective unit type.
     */
    public function elective(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'elective',
        ]);
    }

    /**
     * Create a major unit type.
     */
    public function major(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'major',
        ]);
    }
}
