<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\InitialSetup\InitialSeederRunner;
use Database\Seeders\Timeline\TimelineSeederRunner;
use Database\Seeders\Timeline\CreateActiveStudentsSeeder;
use Database\Seeders\Timeline\EnrollStudentsToProgramSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InitialSeederRunner::class,
            CreateActiveStudentsSeeder::class,

            // Step 2: Enroll students to program
            EnrollStudentsToProgramSeeder::class,

            // Step 3: Open course offerings



            // This seeder should be run manually when permissions are updated
            // UpdatePermissionsSeeder::class,
        ]);
    }
}
