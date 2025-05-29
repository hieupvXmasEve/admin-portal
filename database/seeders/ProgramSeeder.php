<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programs = [
            [
                'name' => 'IT',
                'degree_level' => 'bachelor',
            ],
            [
                'name' => 'Business',
                'degree_level' => 'bachelor',
            ],
            [
                'name' => 'Global Citizen',
                'degree_level' => 'bachelor',
            ],
            [
                'name' => 'Vovinam',
                'degree_level' => 'master',
            ],
            [
                'name' => 'MC',
                'degree_level' => 'phd',
            ],
        ];

        foreach ($programs as $program) {
            Program::create($program);
        }
    }
}
