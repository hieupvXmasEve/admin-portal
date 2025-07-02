<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Phase 1: Core & Campus (Foundation data)
            UserSeeder::class,              // Creates campuses, roles, permissions, users (includes CorePermissionSeeder functionality)
            CampusBuildingSeeder::class,    // Creates buildings for campuses (combines CampusSeeder functionality)
            LectureSeeder::class,           // Creates lecturers
            SemesterSeeder::class,          // Creates semesters (FALL2024, SPRING2025, SUMMER2025) - needed for curriculum

            // Phase 2: Academic & Curriculum (Educational structure)
            ProgramSeeder::class,           // Creates programs and specializations
            UnitSeeder::class,              // Creates units with prerequisite relationships and credit requirements
            SpecializationSeeder::class,    // Creates additional specializations and curriculum versions

            // Phase 3: Academic Offering (Academic scheduling)
            AcademicOfferingSeeder::class,  // Creates course offerings with syllabi and assessments

            // Phase 4: Student Lifecycle & Scenarios (Complex academic scenarios)
            StudentLifecycleSeeder::class,  // Creates registrations, records, attendances, scores with scenarios
        ]);
    }
}
