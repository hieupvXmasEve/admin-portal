<?php

declare(strict_types=1);

namespace Database\Factories\Modules\StudentRegistry\Models;

use App\Models\Student;
use App\Modules\StudentRegistry\Models\StudentGuardianRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentGuardianRelationship>
 */
class StudentGuardianRelationshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'full_name' => fake()->name(),
            'relationship_type' => fake()->randomElement(['father', 'mother', 'guardian', 'other']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'is_primary' => false,
        ];
    }
}
