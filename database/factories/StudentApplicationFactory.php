<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentApplication>
 */
class StudentApplicationFactory extends Factory
{
    protected $model = StudentApplication::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'ethnicity' => fake()->randomElement(['asian', 'caucasian', 'african', 'hispanic', 'other']),
            'birth_day' => fake()->numberBetween(1, 28),
            'birth_month' => fake()->numberBetween(1, 12),
            'birth_year' => fake()->numberBetween(1990, 2005),
            'national_id' => fake()->unique()->numerify('##########'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'health_information' => fake()->optional()->sentence(),
            'campus_code' => fake()->randomElement(['SAI', 'HCM', 'HAN']),
            'intended_program' => fake()->randomElement(['IT', 'BUS', 'ENG', 'DES']),
            'intended_specialization' => fake()->optional()->words(2, true),
            'intake' => fake()->randomElement(['Spring 2025', 'Fall 2025', 'Summer 2025']),
            'exam_date' => fake()->optional()->date(),
            'english_test_type' => fake()->optional()->randomElement(['IELTS', 'TOEFL', 'PTE']),
            'listening' => fake()->optional()->randomFloat(2, 0, 10),
            'reading' => fake()->optional()->randomFloat(2, 0, 10),
            'writing' => fake()->optional()->randomFloat(2, 0, 10),
            'speaking' => fake()->optional()->randomFloat(2, 0, 10),
            'overall' => fake()->optional()->randomFloat(2, 0, 10),
            'study_link_status' => fake()->optional()->randomElement(['pending', 'approved', 'rejected']),
            'english_qualifications' => fake()->optional()->sentence(),
            'sut_id' => fake()->optional()->numerify('SUT######'),
            'is_international_applicant' => fake()->boolean(20),
            'exception_units' => fake()->optional()->sentence(),
            'status' => StudentApplication::STATUS_PENDING,
            'student_id' => null,
            'student_code' => fake()->unique()->numerify('S#######'),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejected_reason' => null,
        ];
    }

    /**
     * Indicate that the application is pending (the default lifecycle state).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentApplication::STATUS_PENDING,
            'student_id' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejected_reason' => null,
        ]);
    }

    /**
     * Indicate that the application has been approved and the student enrolled.
     */
    public function enrolled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentApplication::STATUS_ENROLLED,
            'student_id' => Student::factory(),
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the application has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentApplication::STATUS_REJECTED,
            'student_id' => null,
            'rejected_by' => User::factory(),
            'rejected_at' => now(),
            'rejected_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set specific campus code.
     */
    public function forCampus(string $campusCode): static
    {
        return $this->state(fn (array $attributes) => [
            'campus_code' => $campusCode,
        ]);
    }

    /**
     * Create application with complete birth date.
     */
    public function withBirthDate(int $year, int $month, int $day): static
    {
        return $this->state(fn (array $attributes) => [
            'birth_year' => $year,
            'birth_month' => $month,
            'birth_day' => $day,
        ]);
    }

    /**
     * Create application with English test scores.
     */
    public function withEnglishScores(
        ?float $listening = null,
        ?float $reading = null,
        ?float $writing = null,
        ?float $speaking = null,
        ?float $overall = null
    ): static {
        return $this->state(fn (array $attributes) => [
            'english_test_type' => 'IELTS',
            'listening' => $listening ?? fake()->randomFloat(2, 5, 9),
            'reading' => $reading ?? fake()->randomFloat(2, 5, 9),
            'writing' => $writing ?? fake()->randomFloat(2, 5, 9),
            'speaking' => $speaking ?? fake()->randomFloat(2, 5, 9),
            'overall' => $overall ?? fake()->randomFloat(2, 5, 9),
        ]);
    }

    /**
     * Create international applicant.
     */
    public function international(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_international_applicant' => true,
            'ethnicity' => 'international',
        ]);
    }
}
