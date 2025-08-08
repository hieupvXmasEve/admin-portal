<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Seeder;

class CourseRegistrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assume FALL2025 as the current active semester
        $semester = Semester::where('is_active', true)->first();

        // Retrieve all students with their curriculum units
        $students = Student::with('curriculumUnits')->get();

        // Retrieve all course offerings for FALL2025
        $courseOfferings = CourseOffering::where('semester_id', $semester->id)->get();

        foreach ($students as $student) {
            // Skip students without curriculum units
            if (! $student->curriculumUnits || $student->curriculumUnits->isEmpty()) {
                continue;
            }

            // Retrieve courses linked to the student's curriculum
            $studentCurriculumUnits = $student->curriculumUnits;
            $eligibleCourses = $courseOfferings->filter(function ($courseOffering) use ($studentCurriculumUnits) {
                return $studentCurriculumUnits->contains('id', $courseOffering->curriculum_unit_id);
            });

            foreach ($eligibleCourses as $courseOffering) {
                CourseRegistration::firstOrCreate([
                    'student_code' => $student->id,
                    'course_offering_id' => $courseOffering->id,
                    'semester_id' => $semester->id,
                ], [
                    'registration_status' => 'confirmed',
                    'registration_date' => now()->subWeeks(rand(1, 4)),
                    'registration_method' => 'online',
                    'credit_hours' => (float) $courseOffering->credit_hours,
                ]);
            }
        }
    }
}
