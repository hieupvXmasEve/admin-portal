<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StudentApplication;
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
            'parent_phone' => fake()->phoneNumber(),
            'parent_email' => fake()->safeEmail(),
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
            'submitted_photo' => fake()->boolean(80),
            'submitted_cccd' => fake()->boolean(80),
            'submitted_ccta' => fake()->boolean(80),
            'submitted_tn_translate' => fake()->boolean(70),
            'submitted_hb_translate' => fake()->boolean(70),
            'submitted_other' => fake()->boolean(30),
            'submitted_insurance_card' => fake()->boolean(60),
            'submitted_exemption_gc' => fake()->boolean(20),
            'study_link_status' => fake()->optional()->randomElement(['pending', 'approved', 'rejected']),
            'english_qualifications' => fake()->optional()->sentence(),
            'sut_id' => fake()->optional()->numerify('SUT######'),
            'is_international_applicant' => fake()->boolean(20),
            'exception_units' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'student_id' => null,
            'student_code' => fake()->unique()->regexify('SWU[0-9]{6}'),
        ];
    }

    /**
     * Indicate that the application has been converted to a student.
     */
    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'student_id' => \App\Models\Student::factory(),
        ]);
    }

    /**
     * Indicate that the application is pending conversion.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'student_id' => null,
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
