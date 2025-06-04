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
                'name' => 'Information Technology',
                'code' => 'IT',
                'description' => 'Comprehensive program covering computer science, software engineering, and information systems.',
            ],
            [
                'name' => 'Business Administration',
                'code' => 'BUS',
                'description' => 'Strategic business management with focus on leadership, finance, and operations.',
            ],
            [
                'name' => 'Global Citizenship Studies',
                'code' => 'GCS',
                'description' => 'Interdisciplinary program exploring global issues, cultural awareness, and social responsibility.',
            ],
            [
                'name' => 'Vovinam Martial Arts',
                'code' => 'VMA',
                'description' => 'Traditional Vietnamese martial arts program focusing on philosophy, technique, and cultural heritage.',
            ],
            [
                'name' => 'Media Communications',
                'code' => 'MC',
                'description' => 'Modern communication strategies across digital, print, and broadcast media platforms.',
            ],
        ];

        foreach ($programs as $program) {
            Program::create($program);
        }
    }
}
