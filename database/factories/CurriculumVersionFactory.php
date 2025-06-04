<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurriculumVersion>
 */
class CurriculumVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CurriculumVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'specialization_id' => null, // Can be set when needed
            'version_code' => 'V' . $this->faker->randomFloat(1, 1.0, 9.9),
            'effective_from_semester_id' => null, // Can be set when needed
            'scope' => $this->faker->randomElement(['program', 'specialization']),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    /**
     * Create a curriculum version for a specific program.
     */
    public function forProgram(Program $program): static
    {
        return $this->state(fn(array $attributes) => [
            'program_id' => $program->id,
        ]);
    }

    /**
     * Create a curriculum version for a specific specialization.
     */
    public function forSpecialization(Specialization $specialization): static
    {
        return $this->state(fn(array $attributes) => [
            'program_id' => $specialization->program_id,
            'specialization_id' => $specialization->id,
            'scope' => 'specialization',
        ]);
    }

    /**
     * Create a program-level curriculum version.
     */
    public function programLevel(): static
    {
        return $this->state(fn(array $attributes) => [
            'scope' => 'program',
            'specialization_id' => null,
        ]);
    }

    /**
     * Create a specialization-level curriculum version.
     */
    public function specializationLevel(): static
    {
        return $this->state(fn(array $attributes) => [
            'scope' => 'specialization',
        ]);
    }

    /**
     * Create a curriculum version with an effective semester.
     */
    public function withEffectiveSemester(Semester $semester): static
    {
        return $this->state(fn(array $attributes) => [
            'effective_from_semester_id' => $semester->id,
        ]);
    }
}
