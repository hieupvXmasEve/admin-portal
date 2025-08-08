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

        $name = $this->faker->randomElement($programNames);

        return [
            'name' => $name,
            'code' => strtoupper(substr(str_replace(' ', '', $name), 0, 6)).$this->faker->unique()->numberBetween(100, 999),
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Create a computer science program.
     */
    public function computerScience(): static
    {
        return $this->state(fn () => [
            'name' => 'Computer Science',
        ]);
    }

    /**
     * Create a business administration program.
     */
    public function businessAdministration(): static
    {
        return $this->state(fn () => [
            'name' => 'Business Administration',
        ]);
    }
}
