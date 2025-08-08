<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use Illuminate\Database\Seeder;

class TimelineSeederRunner extends Seeder
{
    /**
     * Run all Timeline seeders in the correct order
     * This follows the educational workflow from student creation to graduation
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Timeline Seeder Runner...');
        $this->command->info('Following academic timeline workflow');

        try {
            // Phase 1: Student Creation and Initial Setup
            $this->command->info("\n📋 Phase 1: Student Creation and Initial Setup");
            $this->call([
                \Database\Seeders\Timeline\CreateActiveStudentsSeeder::class,
            ]);

            // Phase 2: Program Enrollment (ALL eligible students, including first semester)
            $this->command->info("\n🎓 Phase 2: Program Enrollment");
            $this->call([
                \Database\Seeders\Timeline\EnrollStudentsToProgramSeeder::class,
            ]);

            // Phase 3: FALL2024 Semester Setup and Registration
            $this->command->info("\n📅 Phase 3: FALL2024 Semester Setup");
            $this->call([
                \Database\Seeders\Timeline\Fall2024OfferingSeeder::class, // Now includes syllabus creation
                \Database\Seeders\Timeline\InitialCourseRegistrationSeeder::class,
            ]);

            // Phase 4: FALL2024 Academic Activities
            $this->command->info("\n📚 Phase 4: FALL2024 Academic Activities");
            $this->call([
                \Database\Seeders\Timeline\ClassSessionSeeder::class,
                \Database\Seeders\Timeline\AttendanceSeeder::class,
                \Database\Seeders\Timeline\AssessmentScoreSeeder::class,
                \Database\Seeders\Timeline\AcademicRecordSeeder::class,
                \Database\Seeders\Timeline\GPACalculationSeeder::class,
                \Database\Seeders\Timeline\AcademicHoldSeeder::class,
            ]);

            // Phase 5: SPRING2025 Semester Setup
            $this->command->info("\n🌸 Phase 5: SPRING2025 Semester Setup");
            $this->call([
                \Database\Seeders\Timeline\Spring2025RegistrationEligibilitySeeder::class,
                \Database\Seeders\Timeline\Spring2025CourseOfferingSeeder::class, // Now includes syllabus creation
                \Database\Seeders\Timeline\Spring2025RegistrationSeeder::class,
            ]);

            // Phase 6: Advanced Academic Scenarios
            $this->command->info("\n🎯 Phase 6: Advanced Academic Scenarios");
            $this->call([
                \Database\Seeders\Timeline\AcademicLeaveSeeder::class,
                \Database\Seeders\Timeline\CourseExemptionSeeder::class,
                \Database\Seeders\Timeline\AdvancedUnitEligibilitySeeder::class,
                \Database\Seeders\Timeline\MixedSemesterProgressionSeeder::class,
                \Database\Seeders\Timeline\CompletionTrackingSeeder::class,
            ]);

            // Phase 7: Graduation and Special Cases
            $this->command->info("\n🏆 Phase 7: Graduation and Special Cases");
            $this->call([
                \Database\Seeders\Timeline\GraduationEligibilitySeeder::class,
                \Database\Seeders\Timeline\GraduationApplicationSeeder::class,
                \Database\Seeders\Timeline\DropoutScenarioSeeder::class,
                \Database\Seeders\Timeline\TransferStudentSeeder::class,
                \Database\Seeders\Timeline\ReEnrollmentSeeder::class,
                \Database\Seeders\Timeline\AuditTrailSeeder::class,
            ]);

            $this->command->info("\n✅ Timeline Seeder Runner completed successfully!");
            $this->command->info('📊 Academic workflow simulation complete');
        } catch (\Exception $e) {
            $this->command->error("\n❌ Timeline Seeder Runner failed!");
            $this->command->error('Error: '.$e->getMessage());
            throw $e;
        }
    }
}
