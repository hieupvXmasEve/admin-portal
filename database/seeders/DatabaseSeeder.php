<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\InitialSetup\InitialSeederRunner;
use Database\Seeders\Timeline\TimelineSeederRunner;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InitialSeederRunner::class,
            // TimelineSeederRunner::class,

            // This seeder should be run manually when permissions are updated
            // UpdatePermissionsSeeder::class,
        ]);
    }
}
