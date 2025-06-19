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

        // Create only Summer 2025 semester as requested
        $semesters = [
            [
                'code' => 'SUM2025',
                'name' => 'Summer 2025',
                'start_date' => '2025-06-23',
                'end_date' => '2025-10-23',
                'enrollment_start_date' => '2025-06-15 08:00:00',
                'enrollment_end_date' => '2025-06-20 23:59:59',
                'is_active' => true,
                'is_archived' => false,
            ],
        ];

        foreach ($semesters as $semesterData) {
            Semester::create($semesterData);
        }
    }
}
