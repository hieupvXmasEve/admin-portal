<?php

namespace Database\Factories;

use App\Models\Semester;
use App\Models\Syllabus;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Syllabus>
 */
class SyllabusFactory extends Factory
{
    protected $model = Syllabus::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'version' => $this->faker->randomElement(['v1.0', 'v1.1', 'v2.0', 'v2.1', 'v3.0']),
            'description' => $this->faker->paragraphs(3, true),
            'total_hours' => $this->faker->numberBetween(30, 180),
            'hours_per_session' => $this->faker->numberBetween(1, 4),
            'effective_from_semester_id' => Semester::factory(),
            'is_active' => $this->faker->boolean(70), // 70% chance of being active
        ];
    }

    /**
     * Indicate that the syllabus is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the syllabus is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
