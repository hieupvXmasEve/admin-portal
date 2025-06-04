<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Program::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $programNames = [
            'Computer Science',
            'Information Technology',
            'Software Engineering',
            'Business Administration',
            'Marketing',
            'Accounting',
            'Engineering',
            'Psychology',
            'Mathematics',
            'Physics',
        ];

        return [
            'name' => $this->faker->randomElement($programNames),
            'degree_level' => $this->faker->randomElement(['bachelor', 'master', 'phd']),
        ];
    }

    /**
     * Create a bachelor's degree program.
     */
    public function bachelor(): static
    {
        return $this->state(fn(array $attributes) => [
            'degree_level' => 'bachelor',
        ]);
    }

    /**
     * Create a master's degree program.
     */
    public function master(): static
    {
        return $this->state(fn(array $attributes) => [
            'degree_level' => 'master',
        ]);
    }

    /**
     * Create a PhD program.
     */
    public function phd(): static
    {
        return $this->state(fn(array $attributes) => [
            'degree_level' => 'phd',
        ]);
    }

    /**
     * Create a computer science program.
     */
    public function computerScience(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Computer Science',
        ]);
    }

    /**
     * Create a business administration program.
     */
    public function businessAdministration(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Business Administration',
        ]);
    }
}
