<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicRecord>
 */
class AcademicRecordFactory extends Factory
{
    protected $model = AcademicRecord::class;

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
            'unit_id' => Unit::factory(),
            'program_id' => Program::factory(),
            'campus_id' => Campus::factory(),
            'final_percentage' => $this->faker->randomFloat(2, 50, 100),
            'final_letter_grade' => $this->faker->randomElement(['HD', 'D', 'C', 'P']),
            'grade_points' => $this->faker->randomFloat(2, 1.0, 4.0),
            'quality_points' => $this->faker->randomFloat(2, 4.0, 16.0),
            'credit_hours' => 3,
            'credit_hours_earned' => 3,
            'credit_points' => 10,
            'credit_points_earned' => 10,
            'grade_status' => 'final',
            'completion_status' => 'completed',
            'completion_date' => now(),
            'excluded_from_gpa' => false,
            'is_passed' => true,
        ];
    }
}
