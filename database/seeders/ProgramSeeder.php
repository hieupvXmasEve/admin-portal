<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Specialization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Business program (for SpecializationSeeder compatibility)
        $businessProgram = Program::create([
            'name' => 'Business',
            'code' => 'BUS',
            'description' => 'Comprehensive business administration program covering business administration topics and advanced business administration systems.',
        ]);

        // Create IT program (for SpecializationSeeder compatibility)
        $itProgram = Program::create([
            'name' => 'IT',
            'code' => 'IT',
            'description' => 'Comprehensive information technology program covering programming, algorithms, data structures, and software engineering.',
        ]);
    }
}
