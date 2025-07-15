<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Semester;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates academic semesters for the academic year
     */
    public function run(): void
    {
        $this->command->info('📅 Creating academic semesters...');

        // Clean existing data (use delete instead of truncate due to foreign key constraints)
        Semester::query()->delete();

        // Create semesters for 2024-2025 academic year
        $this->createAcademicYearSemesters();

        $this->command->info('✅ Academic semesters created successfully!');
    }

    private function createAcademicYearSemesters(): void
    {
        $currentDate = Carbon::now();

        $semesters = [
            [
                'name' => 'Fall Semester 2025',
                'code' => 'FALL2025',
                'start_date' => '2025-08-01',
                'end_date' => '2025-12-31',
                'enrollment_start_date' => '2025-07-01',
                'enrollment_end_date' => '2025-07-31',
                'is_active' => true, // Upcoming semester (registration open)
                'is_archived' => false,
            ],
            [
                'name' => 'Spring Semester 2026',
                'code' => 'SPRING2026',
                'start_date' => '2026-01-15',
                'end_date' => '2026-05-20',
                'enrollment_start_date' => '2025-12-01',
                'enrollment_end_date' => '2026-01-10',
                'is_active' => false, // Future semester
                'is_archived' => false,
            ],
            [
                'name' => 'Summer Semester 2026',
                'code' => 'SUMMER2026',
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-10',
                'enrollment_start_date' => '2026-05-01',
                'enrollment_end_date' => '2026-05-25',
                'is_active' => false, // Future semester
                'is_archived' => false,
            ],
        ];

        foreach ($semesters as $semesterData) {
            $semester = Semester::create($semesterData);
            $this->command->info("  ✓ Created semester: {$semester->name} ({$semester->code})");
        }

        $this->command->info("  📊 Total semesters created: " . count($semesters));
    }
}
