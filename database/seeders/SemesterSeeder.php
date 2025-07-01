<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Semester;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing semesters
        Semester::query()->delete();

        // Create 3 semesters as per seeder plan: past, current, future
        $semesters = [
            // Past semester (already ended)
            [
                'code' => 'FALL2024',
                'name' => 'Fall 2024',
                'start_date' => '2024-08-01',
                'end_date' => '2024-12-15',
                'enrollment_start_date' => '2024-07-15 08:00:00',
                'enrollment_end_date' => '2024-07-30 23:59:59',
                'is_active' => false,
                'is_archived' => true,
            ],
            // Current semester (ongoing)
            [
                'code' => 'SPRING2025',
                'name' => 'Spring 2025',
                'start_date' => '2025-02-01',
                'end_date' => '2025-06-15',
                'enrollment_start_date' => '2025-01-15 08:00:00',
                'enrollment_end_date' => '2025-01-30 23:59:59',
                'is_active' => true,
                'is_archived' => false,
            ],
            // Future semester
            [
                'code' => 'SUMMER2025',
                'name' => 'Summer 2025',
                'start_date' => '2025-06-23',
                'end_date' => '2025-10-23',
                'enrollment_start_date' => '2025-06-15 08:00:00',
                'enrollment_end_date' => '2025-06-20 23:59:59',
                'is_active' => false,
                'is_archived' => false,
            ],
        ];

        foreach ($semesters as $semesterData) {
            Semester::create($semesterData);
        }

        $this->command->info('Created 3 semesters: FALL2024 (past), SPRING2025 (current), SUMMER2025 (future)');
    }
}
