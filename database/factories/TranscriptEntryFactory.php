<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptEntry>
 */
class TranscriptEntryFactory extends Factory
{
    protected $model = TranscriptEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_result_id' => fake()->unique()->numberBetween(1, PHP_INT_MAX),
            'student_id' => Student::factory()->state([
                'intake' => 1,
                'intake_mode' => 'sequential',
                'intake_semester_id' => Semester::factory(),
                'status' => 'intake_course',
            ]),
            'course_offering_id' => CourseOffering::factory(),
            'semester_id' => Semester::factory(),
            'unit_id' => Unit::factory(),
            'program_id' => Program::factory(),
            'campus_id' => Campus::factory(),
            'attempt_number' => 1,
            'final_percentage' => 80,
            'final_letter_grade' => 'D',
            'credit_points' => 3,
            'credit_points_earned' => 3,
            'quality_points' => 240,
            'is_passed' => true,
            'excluded_from_gpa' => false,
            'affects_academic_standing' => true,
            'affects_graduation_requirement' => true,
            'satisfies_prerequisite' => true,
            'finalized_at' => now(),
        ];
    }
}
