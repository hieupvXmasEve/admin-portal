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
            UserSeeder::class,                    // Creates campuses and users first
            ComprehensiveEducationSeeder::class,  // Creates complete education structure:
            // - 50 units
            // - 5 programs with 3 specializations each
            // - 5 curriculum versions per specialization
            // - 15 curriculum units per version
            // - 9 semesters over 3 years
            // - 3 unit types: core, elective, major
            SyllabusSeeder::class,               // Creates syllabus (needs units and semesters)

            // Legacy seeders (commented out - replaced by ComprehensiveEducationSeeder)
            // SemesterSeeder::class,           // Creates semesters with new fields
            // UnitSeeder::class,               // Creates units
            // ProgramSeeder::class,            // Creates programs
            // CurriculumUnitTypeSeeder::class, // Creates curriculum unit types (core, elective, major)
            // CurriculumSchemaSeeder::class,   // Creates complete curriculum structure
            // CurriculumSeeder::class,         // Creates units and basic curriculum structure (existing)
            // SpecializationSeeder::class,     // Creates specializations and more units (existing)
        ]);
    }
}
