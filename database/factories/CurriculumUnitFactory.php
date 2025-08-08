<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
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
            'type' => $this->faker->randomElement(['core', 'major', 'elective']),
            'semester_number' => $this->faker->optional()->numberBetween(1, 8),
            'is_compulsory' => $this->faker->boolean(70), // 70% chance of being compulsory
            'note' => $this->faker->optional()->text(200),
        ];
    }

    /**
     * Create a core curriculum unit.
     */
    public function core(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'core',
            'is_compulsory' => true,
            'note' => 'Core curriculum unit',
        ]);
    }

    /**
     * Create an elective curriculum unit.
     */
    public function elective(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'elective',
            'is_compulsory' => false,
            'note' => 'Elective curriculum unit',
        ]);
    }

    /**
     * Create a major curriculum unit.
     */
    public function major(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'major',
            'is_compulsory' => true,
            'note' => 'Major curriculum unit',
        ]);
    }

    /**
     * Create a curriculum unit for a specific semester.
     */
    public function forSemester(int $semester): static
    {
        return $this->state(fn (array $attributes) => [
            'semester_number' => $semester,
        ]);
    }

    /**
     * Create a curriculum unit with special note.
     */
    public function withNote(string $note): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $note,
        ]);
    }
}
