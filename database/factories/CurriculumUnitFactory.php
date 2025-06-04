<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\CurriculumUnitType;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurriculumUnit>
 */
class CurriculumUnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CurriculumUnit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curriculum_version_id' => CurriculumVersion::factory(),
            'unit_id' => Unit::factory(),
            'unit_type_id' => CurriculumUnitType::factory(),
            'semester_order' => $this->faker->optional()->numberBetween(1, 8),
            'is_compulsory' => $this->faker->boolean(70), // 70% chance of being compulsory
            'note' => $this->faker->optional(30)->sentence(),
            'group_type' => $this->faker->randomElement(['core', 'major', 'elective', 'minor', 'second_major']),
            'unit_scope' => $this->faker->randomElement(['common', 'specialization_specific', 'cross_program']),
            'is_required' => $this->faker->boolean(60), // 60% chance of being required
            'year_level' => $this->faker->optional()->numberBetween(1, 4),
            'semester_number' => $this->faker->optional()->numberBetween(1, 2),
            'minimum_grade' => $this->faker->optional()->randomFloat(2, 2.0, 4.0),
            'special_conditions' => $this->faker->optional(20)->sentence(),
            'allows_concurrent_enrollment' => $this->faker->boolean(30), // 30% chance of allowing concurrent enrollment
        ];
    }

    /**
     * Create a core curriculum unit.
     */
    public function core(): static
    {
        return $this->state(fn(array $attributes) => [
            'group_type' => 'core',
            'is_required' => true,
            'is_compulsory' => true,
        ]);
    }

    /**
     * Create an elective curriculum unit.
     */
    public function elective(): static
    {
        return $this->state(fn(array $attributes) => [
            'group_type' => 'elective',
            'is_required' => false,
            'is_compulsory' => false,
        ]);
    }

    /**
     * Create a major curriculum unit.
     */
    public function major(): static
    {
        return $this->state(fn(array $attributes) => [
            'group_type' => 'major',
            'is_required' => true,
            'is_compulsory' => true,
        ]);
    }

    /**
     * Create a curriculum unit for a specific year and semester.
     */
    public function forYearAndSemester(int $year, int $semester): static
    {
        return $this->state(fn(array $attributes) => [
            'year_level' => $year,
            'semester_number' => $semester,
            'semester_order' => (($year - 1) * 2) + $semester,
        ]);
    }

    /**
     * Create a curriculum unit that allows concurrent enrollment.
     */
    public function allowsConcurrent(): static
    {
        return $this->state(fn(array $attributes) => [
            'allows_concurrent_enrollment' => true,
        ]);
    }
}
