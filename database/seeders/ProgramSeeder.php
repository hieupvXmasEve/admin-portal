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
        // Create Bachelor of Business Administration program (BA)
        $baProgram = Program::create([
            'name' => 'Bachelor of Business Administration',
            'code' => 'BA',
            'description' => 'Comprehensive business administration program covering business administration topics and advanced business administration systems.',
        ]);

        // Create Business Administration specialization (BA)
        Specialization::create([
            'program_id' => $baProgram->id,
            'name' => 'Business Administration',
            'code' => 'BA',
            'description' => 'Specialization focusing on core business administration topics and advanced business administration systems.',
            'is_active' => true,
        ]);

        // Create Bachelor of Computer Science program (BCS)
        $bcsProgram = Program::create([
            'name' => 'Bachelor of Computer Science',
            'code' => 'BCS',
            'description' => 'Comprehensive computer science program covering programming, algorithms, data structures, and software engineering.',
        ]);

        // Create Computer Science specialization (BCS-CS)
        Specialization::create([
            'program_id' => $bcsProgram->id,
            'name' => 'Computer Science',
            'code' => 'BCS-CS',
            'description' => 'Specialization focusing on core computer science topics including programming, algorithms, and software development.',
            'is_active' => true,
        ]);
    }
}
