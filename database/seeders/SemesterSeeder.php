<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Semester;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $semesters = [
            // Current Academic Year 2024-2025
            [
                'code' => 'FALL2024',
                'name' => 'Fall 2024',
                'start_date' => '2024-08-26',
                'end_date' => '2024-12-15',
                'enrollment_start_date' => '2024-08-01 08:00:00',
                'enrollment_end_date' => '2024-08-25 23:59:59',
                'is_active' => true,
                'is_archived' => false,
            ],
            [
                'code' => 'SPR2025',
                'name' => 'Spring 2025',
                'start_date' => '2025-01-13',
                'end_date' => '2025-05-10',
                'enrollment_start_date' => '2024-12-01 08:00:00',
                'enrollment_end_date' => '2025-01-12 23:59:59',
                'is_active' => false,
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

            // Previous Academic Year 2023-2024 (Archived)
            [
                'code' => 'FALL2023',
                'name' => 'Fall 2023',
                'start_date' => '2023-08-28',
                'end_date' => '2023-12-17',
                'enrollment_start_date' => '2023-08-01 08:00:00',
                'enrollment_end_date' => '2023-08-27 23:59:59',
                'is_active' => false,
                'is_archived' => true,
            ],
            [
                'code' => 'SPR2024',
                'name' => 'Spring 2024',
                'start_date' => '2024-01-15',
                'end_date' => '2024-05-12',
                'enrollment_start_date' => '2023-12-01 08:00:00',
                'enrollment_end_date' => '2024-01-14 23:59:59',
                'is_active' => false,
                'is_archived' => true,
            ],

            // Intersession
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
