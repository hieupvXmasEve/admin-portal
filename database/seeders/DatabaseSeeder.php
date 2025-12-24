<?php

namespace Database\Seeders;

use Database\Seeders\InitialSetup\InitialSeederRunner;
use Database\Seeders\Timeline\CreateActiveStudentsSeeder;
use Database\Seeders\Timeline\EnrollStudentsToProgramSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InitialSeederRunner::class,
            DepartmentSeeder::class,
            //            CreateActiveStudentsSeeder::class,

            // Step 2: Enroll students to program
            //            EnrollStudentsToProgramSeeder::class,

            // Step 3: Open course offerings
            //            CourseOfferingsSeeder::class,

            // Step 4: Register students to course offerings
            //            CourseRegistrationSeeder::class,

            // Step 5: Generate class sessions for course offerings
            //            ClassSessionSeeder::class,

            // This seeder should be run manually when permissions are updated
            // UpdatePermissionsSeeder::class,
        ]);
    }
}
