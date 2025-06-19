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
            LectureSeeder::class,       // Creates lecturers
            ProgramSeeder::class,       // Creates Bachelor of Computer Science program and AI specialization
            SemesterSeeder::class,      // Creates Summer 2025 semester
            UnitSeeder::class,          // Creates Bachelor of Computer Science units
            CurriculumSeeder::class,    // Creates curriculum version for Summer 2025,
            SyllabusSeeder::class,      // Creates syllabi for the units
        ]);
    }
}
