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
        // Clear existing programs and specializations
        Specialization::query()->delete();
        Program::query()->delete();

        // Create Bachelor of Business Administration program (BA)
        $program = Program::create([
            'name' => 'Bachelor of Business Administration',
            'code' => 'BA',
            'description' => 'Comprehensive business administration program covering business administration topics and advanced business administration systems.',
        ]);

        // Create Business Administration specialization (BA)
        Specialization::create([
            'program_id' => $program->id,
            'name' => 'Business Administration',
            'code' => 'BA',
            'description' => 'Specialization focusing on core business administration topics and advanced business administration systems.',
            'is_active' => true,
        ]);
    }
}
