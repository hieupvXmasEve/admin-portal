<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationGuardian>
 */
class ApplicationGuardianFactory extends Factory
{
    protected $model = ApplicationGuardian::class;

    public function definition(): array
    {
        return [
            'student_application_id' => StudentApplication::factory(),
            'full_name' => fake()->name(),
            'relationship' => fake()->randomElement(ApplicationGuardian::relationships()),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'occupation' => fake()->optional()->jobTitle(),
            'address' => fake()->optional()->address(),
            'is_primary' => false,
        ];
    }

    /**
     * Mark this Guardian as the primary one for its Application.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Attach this Guardian to a specific Application.
     */
    public function forApplication(StudentApplication $application): static
    {
        return $this->state(fn (array $attributes) => [
            'student_application_id' => $application->id,
        ]);
    }
}
