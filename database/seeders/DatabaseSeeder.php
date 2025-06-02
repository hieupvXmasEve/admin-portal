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
            UserSeeder::class,          // Creates campuses and users first
            SemesterSeeder::class,      // Creates semesters with new fields
            SyllabusSeeder::class,      // Creates syllabus (needs units and semesters)
            UnitSeeder::class,          // Creates units
            // ProgramSeeder::class,       // Creates programs
            // CurriculumSeeder::class,    // Creates units and basic curriculum structure
            // SpecializationSeeder::class, // Creates specializations and more units
        ]);
    }
}
