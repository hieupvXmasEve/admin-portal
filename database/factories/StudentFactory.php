<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'student_code' => fake()->unique()->numerify('STU########'),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'nationality' => fake()->randomElement(['vietnamese', 'american', 'australian', 'other']),
            'national_id' => fake()->unique()->numerify('##########'),
            'address' => fake()->address(),
            'campus_id' => Campus::factory(),
            'program_id' => Program::factory(),
            'curriculum_version_id' => CurriculumVersion::factory(),
            'admission_date' => fake()->date(),
            'status' => 'active',
            'academic_status' => 'active',
        ];
    }

    /**
     * Indicate that the student is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'academic_status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the student is graduated.
     */
    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graduated',
            'academic_status' => 'graduated',
        ]);
    }

    /**
     * Set specific campus for the student.
     */
    public function forCampus(Campus $campus): static
    {
        return $this->state(fn (array $attributes) => [
            'campus_id' => $campus->id,
        ]);
    }

    /**
     * Set specific program for the student.
     */
    public function forProgram(Program $program): static
    {
        return $this->state(fn (array $attributes) => [
            'program_id' => $program->id,
        ]);
    }

    /**
     * Set specialization for the student.
     */
    public function withSpecialization(Specialization $specialization): static
    {
        return $this->state(fn (array $attributes) => [
            'specialization_id' => $specialization->id,
            'program_id' => $specialization->program_id,
        ]);
    }
}
