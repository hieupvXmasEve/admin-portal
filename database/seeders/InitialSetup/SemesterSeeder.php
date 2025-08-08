<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

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
                'name' => 'Fall 2025 - 20252027',
                'code' => 'FALL2025',
                'start_date' => '2025-09-01',
                'end_date' => '2025-12-14',
                'enrollment_start_date' => null,
                'enrollment_end_date' => null,
                'is_active' => false, // Upcoming semester (registration open)
                'is_archived' => false,
            ],
            [
                'name' => 'Spring 2026 - 20262028',
                'code' => 'SPR2026',
                'start_date' => '2025-12-29',
                'end_date' => '2026-04-12',
                'enrollment_start_date' => null,
                'enrollment_end_date' => null,
                'is_active' => false, // Future semester
                'is_archived' => false,
            ],
            [
                'name' => 'Summer 2026 - 20262028',
                'code' => 'SUM2026',
                'start_date' => '2026-04-27',
                'end_date' => '2026-08-02',
                'enrollment_start_date' => null,
                'enrollment_end_date' => null,
                'is_active' => false, // Future semester
                'is_archived' => false,
            ],
        ];

        foreach ($semesters as $semesterData) {
            $semester = Semester::create($semesterData);
            $this->command->info("  ✓ Created semester: {$semester->name} ({$semester->code})");
        }

        $this->command->info('  📊 Total semesters created: '.count($semesters));
    }
}
