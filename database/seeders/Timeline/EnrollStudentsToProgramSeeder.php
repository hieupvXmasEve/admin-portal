<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class EnrollStudentsToProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Enrolls ALL eligible students into their programs and curriculum versions
     * This includes first-time students and any returning students
     */
    public function run(): void
    {
        $this->command->info('📚 Enrolling students into programs...');

        // Get all eligible students (admitted, active, or suspended status)
        $eligibleStudents = Student::whereIn('status', ['admitted', 'active', 'suspended'])->get();

        if ($eligibleStudents->isEmpty()) {
            throw new \Exception('No eligible students found. Please run previous seeders first.');
        }

        // Get the first semester (FALL2024) - should already exist from SemesterSeeder
        $firstSemester = Semester::where('is_active', true)->first();

        if (!$firstSemester) {
            throw new \Exception('FALL2024 semester not found. Please run InitialSetup seeders first.');
        }

        $enrollmentCount = 0;
        $newlyActivatedCount = 0;

        foreach ($eligibleStudents as $student) {
            // Check if student already has enrollment for this semester
            $existingEnrollment = Enrollment::where('student_code', $student->id)
                ->where('semester_id', $firstSemester->id)
                ->first();

            if (!$existingEnrollment) {
                $semesterNumber = Semester::query()
                    ->whereDate('start_date', '>=', $student->admission_date)
                    ->whereDate('start_date', '<=', $firstSemester->start_date)
                    ->orderBy('start_date')
                    ->count();

                // Create enrollment record
                Enrollment::create([
                    'student_code' => $student->id,
                    'semester_id' => $firstSemester->id,
                    'curriculum_version_id' => $student->curriculum_version_id,
                    'semester_number' => $semesterNumber,
                    'status' => 'in_progress',
                    'notes' => 'Initial enrollment into program for semester #' . $semesterNumber,
                ]);

                $enrollmentCount++;
            }

            // Update student status to active if they were admitted (don't change suspended students)
            if ($student->status === 'admitted') {
                $student->update([
                    'status' => 'active',
                ]);
                $newlyActivatedCount++;
            }

            if ($enrollmentCount % 20 === 0) {
                $this->command->info("  Enrolled {$enrollmentCount} students...");
            }
        }

        $this->command->info("✅ Successfully processed {$enrollmentCount} enrollments!");
        $this->command->info("  📅 Semester: {$firstSemester->name}");
        $this->command->info("  🎓 Newly activated students: {$newlyActivatedCount}");
        $this->command->info("  📊 All eligible students now have enrollment records");
    }
}
