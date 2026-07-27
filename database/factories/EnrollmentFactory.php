<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurriculumVersion;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Enrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'semester_id' => Semester::factory(),
            'curriculum_version_id' => CurriculumVersion::factory(),
            'semester_number' => fake()->numberBetween(1, 8),
            'status' => 'in_progress',
        ];
    }
}
