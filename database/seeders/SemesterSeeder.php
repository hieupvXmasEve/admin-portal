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
        $semesters = [
            // Academic Year 2025
            [
                'code' => 'SP2025',
                'name' => 'Spring 2025',
                'start_date' => '2025-01-13',
                'end_date' => '2025-05-10',
                'enrollment_start_date' => '2024-12-01 08:00:00',
                'enrollment_end_date' => '2025-01-12 23:59:59',
                'is_active' => true,
                'is_archived' => false,
            ],
            [
                'code' => 'SUM2025',
                'name' => 'Summer 2025',
                'start_date' => '2025-06-02',
                'end_date' => '2025-08-15',
                'enrollment_start_date' => '2025-04-01 08:00:00',
                'enrollment_end_date' => '2025-06-01 23:59:59',
                'is_active' => false,
                'is_archived' => false,
            ],
            [
                'code' => 'FAL2025',
                'name' => 'Fall 2025',
                'start_date' => '2025-08-25',
                'end_date' => '2025-12-14',
                'enrollment_start_date' => '2025-07-01 08:00:00',
                'enrollment_end_date' => '2025-08-24 23:59:59',
                'is_active' => false,
                'is_archived' => false,
            ],

            // Academic Year 2024 (Previous/Current)
            [
                'code' => 'SP2024',
                'name' => 'Spring 2024',
                'start_date' => '2024-01-15',
                'end_date' => '2024-05-12',
                'enrollment_start_date' => '2023-12-01 08:00:00',
                'enrollment_end_date' => '2024-01-14 23:59:59',
                'is_active' => false,
                'is_archived' => true,
            ],
            [
                'code' => 'SUM2024',
                'name' => 'Summer 2024',
                'start_date' => '2024-06-03',
                'end_date' => '2024-08-16',
                'enrollment_start_date' => '2024-04-01 08:00:00',
                'enrollment_end_date' => '2024-06-02 23:59:59',
                'is_active' => false,
                'is_archived' => true,
            ],
            [
                'code' => 'FAL2024',
                'name' => 'Fall 2024',
                'start_date' => '2024-08-26',
                'end_date' => '2024-12-15',
                'enrollment_start_date' => '2024-08-01 08:00:00',
                'enrollment_end_date' => '2024-08-25 23:59:59',
                'is_active' => false,
                'is_archived' => true,
            ],

            // Academic Year 2026 (Future planning)
            [
                'code' => 'SP2026',
                'name' => 'Spring 2026',
                'start_date' => '2026-01-12',
                'end_date' => '2026-05-09',
                'enrollment_start_date' => '2025-12-01 08:00:00',
                'enrollment_end_date' => '2026-01-11 23:59:59',
                'is_active' => false,
                'is_archived' => false,
            ],
            [
                'code' => 'SUM2026',
                'name' => 'Summer 2026',
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-14',
                'enrollment_start_date' => '2026-04-01 08:00:00',
                'enrollment_end_date' => '2026-05-31 23:59:59',
                'is_active' => false,
                'is_archived' => false,
            ],
            [
                'code' => 'FAL2026',
                'name' => 'Fall 2026',
                'start_date' => '2026-08-24',
                'end_date' => '2026-12-13',
                'enrollment_start_date' => '2026-07-01 08:00:00',
                'enrollment_end_date' => '2026-08-23 23:59:59',
                'is_active' => false,
                'is_archived' => false,
            ],

            // Intersession periods
            [
                'code' => 'INT2025',
                'name' => 'Intersession 2025',
                'start_date' => '2025-01-02',
                'end_date' => '2025-01-10',
                'enrollment_start_date' => '2024-12-01 08:00:00',
                'enrollment_end_date' => '2025-01-01 23:59:59',
                'is_active' => false,
                'is_archived' => false,
            ],
        ];

        foreach ($semesters as $semesterData) {
            // Check if semester already exists
            $existing = Semester::where('code', $semesterData['code'])->first();

            if (!$existing) {
                Semester::create($semesterData);
            }
        }
    }
}
