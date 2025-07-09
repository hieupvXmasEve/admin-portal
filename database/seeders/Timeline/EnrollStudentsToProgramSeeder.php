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

        // Get all eligible students (admitted or active status)
        $eligibleStudents = Student::whereIn('status', ['admitted', 'active'])->get();

        if ($eligibleStudents->isEmpty()) {
            throw new \Exception('No eligible students found. Please run previous seeders first.');
        }

        // Get or create the first semester (FALL2024)
        $firstSemester = Semester::where('code', 'FALL2024')->first();

        if (!$firstSemester) {
            // Create FALL2024 semester
            $firstSemester = Semester::create([
                'name' => 'Fall Semester 2024',
                'code' => 'FALL2024',
                'start_date' => '2024-08-01',
                'end_date' => '2024-12-15',
                'enrollment_start_date' => '2024-07-01',
                'enrollment_end_date' => '2024-07-31',
                'is_active' => true,
                'is_archived' => false,
            ]);
            $this->command->info("  📅 Created FALL2024 semester");
        }

        $enrollmentCount = 0;
        $newlyActivatedCount = 0;

        foreach ($eligibleStudents as $student) {
            // Check if student already has enrollment for this semester
            $existingEnrollment = Enrollment::where('student_id', $student->id)
                ->where('semester_id', $firstSemester->id)
                ->first();

            if (!$existingEnrollment) {
                // Create enrollment record
                $enrollment = Enrollment::create([
                    'student_id' => $student->id,
                    'semester_id' => $firstSemester->id,
                    'curriculum_version_id' => $student->curriculum_version_id,
                    'semester_number' => 1, // First semester
                    'status' => 'in_progress',
                    'notes' => 'Initial enrollment into program for first semester',
                ]);

                $enrollmentCount++;
            }

            // Update student status to active if they were admitted
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
