<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use Illuminate\Database\Seeder;

class InitialSeederRunner extends Seeder
{
    /**
     * Run the initial setup seeders in the correct order.
     * This should be run before creating students.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Initial Setup Seeders...');

        $this->call([
            // 001. Create campus, buildings, and rooms
            InstitutionSetupSeeder::class,

            // 002. Create roles and permissions
            RoleAndPermissionSeeder::class,

            // 003. Create Super Admin, Admin, Staff accounts
            UserAccountSeeder::class,

            // 004. Create lecturer user accounts and profiles
            //            LecturerSeeder::class,

            // 005. Create programs, specializations, and units
            // AcademicStructureSeeder::class,

            // 006. Create academic semesters for the year
            // SemesterSeeder::class,

            // 007. Link units to programs and graduation requirements
            //            CurriculumSeeder::class,

            // // 008. Create syllabus and assessment components for each class
            //            SyllabusSeeder::class,
        ]);

        $this->command->info('✅ Initial Setup Seeders completed successfully!');
        $this->command->info('📝 Ready to create students and enrollment data.');
    }
}
