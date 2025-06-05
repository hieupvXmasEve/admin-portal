<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Program;
use App\Models\Specialization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specialization>
 */
class SpecializationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Specialization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'name' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->lexify('???-??')),
            'description' => $this->faker->optional()->paragraph(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }

    /**
     * Create a specialization for a specific program.
     */
    public function forProgram(Program $program): static
    {
        return $this->state(fn(array $attributes) => [
            'program_id' => $program->id,
        ]);
    }

    /**
     * Create an active specialization.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive specialization.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a software development specialization.
     */
    public function softwareDevelopment(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Software Development',
            'code' => 'IT-SD',
            'description' => 'Focuses on application development, programming languages, and software engineering principles.',
            'is_active' => true,
        ]);
    }

    /**
     * Create a cybersecurity specialization.
     */
    public function cybersecurity(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Cybersecurity',
            'code' => 'IT-CS',
            'description' => 'Specialized track focusing on information security, network security, and digital forensics.',
            'is_active' => true,
        ]);
    }

    /**
     * Create a data science specialization.
     */
    public function dataScience(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Data Science',
            'code' => 'IT-DS',
            'description' => 'Combines statistics, programming, and domain expertise to extract insights from data.',
            'is_active' => true,
        ]);
    }

    /**
     * Create a marketing specialization.
     */
    public function marketing(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Marketing',
            'code' => 'BUS-MKT',
            'description' => 'Focuses on digital marketing, consumer behavior, and brand management.',
            'is_active' => true,
        ]);
    }

    /**
     * Create a finance specialization.
     */
    public function finance(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Finance',
            'code' => 'BUS-FIN',
            'description' => 'Covers financial analysis, investment strategies, and corporate finance.',
            'is_active' => true,
        ]);
    }
}
