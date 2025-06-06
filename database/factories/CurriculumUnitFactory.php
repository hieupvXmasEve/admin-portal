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
            'unit_type_id' => $this->faker->optional()->randomElement(
                CurriculumUnitType::pluck('id')->toArray() ?: [null]
            ),
            'year_level' => $this->faker->optional()->numberBetween(1, 4),
            'semester_number' => $this->faker->optional()->numberBetween(1, 2),
            'note' => $this->faker->optional()->text(200),
        ];
    }

    /**
     * Create a core curriculum unit.
     */
    public function core(): static
    {
        return $this->state(fn(array $attributes) => [
            'note' => 'Core curriculum unit',
        ]);
    }

    /**
     * Create an elective curriculum unit.
     */
    public function elective(): static
    {
        return $this->state(fn(array $attributes) => [
            'note' => 'Elective curriculum unit',
        ]);
    }

    /**
     * Create a major curriculum unit.
     */
    public function major(): static
    {
        return $this->state(fn(array $attributes) => [
            'note' => 'Major curriculum unit',
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
        ]);
    }

    /**
     * Create a curriculum unit with special note.
     */
    public function withNote(string $note): static
    {
        return $this->state(fn(array $attributes) => [
            'note' => $note,
        ]);
    }
}
