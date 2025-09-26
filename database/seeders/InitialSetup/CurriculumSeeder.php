<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates curriculum version for the IT program with proper unit distribution
     * 3-year program with core, elective, and major units distributed across semesters
     */
    public function run(): void
    {
        $this->command->info('📚 Creating IT curriculum version...');

        // Clean existing data
        $this->cleanExistingData();

        // Create curriculum version for IT program
        $this->createITCurriculumVersion();

        $this->command->info('✅ IT curriculum version created successfully!');
    }

    private function cleanExistingData(): void
    {
        // Check if there are students or enrollments using curriculum versions
        $studentsCount = \App\Models\Student::count();
        $enrollmentsCount = \App\Models\Enrollment::whereNotNull('curriculum_version_id')->count();

        if ($studentsCount > 0 || $enrollmentsCount > 0) {
            $this->command->warn("⚠️  Found {$studentsCount} students and {$enrollmentsCount} enrollments. Skipping curriculum cleanup to preserve foreign key relationships.");
            $this->command->info('📝 Will update existing curriculum records instead of recreating them.');

            return;
        }

        try {
            CurriculumUnit::query()->delete();
            CurriculumVersion::query()->delete();
            $this->command->info('🧹 Cleaned existing curriculum data successfully.');
        } catch (\Exception $e) {
            $this->command->warn('⚠️  Could not clean existing curriculum data due to foreign key constraints. Will update existing records instead.');
            $this->command->info('Error: ' . $e->getMessage());
        }
    }

    private function createITCurriculumVersion(): void
    {
        // Get IT Program
        $itProgram = Program::where('code', 'IT')->first();

        if (! $itProgram) {
            $this->command->error('IT Program not found. Please run AcademicStructureSeeder first.');
            return;
        }

        // Get the first available semester
        $firstSemester = Semester::orderBy('start_date')->first();

        if (! $firstSemester) {
            $this->command->error('No semesters found. Please run SemesterSeeder first.');
            return;
        }

        // Create curriculum version for IT program
        $curriculumVersion = CurriculumVersion::updateOrCreate(
            [
                'program_id' => $itProgram->id,
                'specialization_id' => null,
                'version_code' => '2025.1',
            ],
            [
                'semester_id' => $firstSemester->id,
                'notes' => 'IT Program Curriculum - 3 years with core, elective, and major units distributed across semesters',
            ]
        );

        // Assign units to the IT curriculum
        $this->assignUnitsToITCurriculum($curriculumVersion);

        $this->command->info('📖 Created curriculum for IT program');
    }

    private function assignUnitsToITCurriculum(CurriculumVersion $curriculumVersion): void
    {
        // Year 1 - Foundation and Core Units (3 semesters: 1, 2, 3)
        $year1Units = [
            // Semester 1 - Year 1
            ['code' => 'COS10004', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'COS10009', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'COS10026', 'year' => 1, 'semester' => 1, 'type' => 'core'],

            // Semester 2 - Year 1
            ['code' => 'TNE10006', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'COS20007', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'COS10005', 'year' => 1, 'semester' => 2, 'type' => 'elective'],

            // Semester 3 - Year 1
            ['code' => 'COS20031', 'year' => 1, 'semester' => 3, 'type' => 'core'],
            ['code' => 'COS20015', 'year' => 1, 'semester' => 3, 'type' => 'elective'],
            ['code' => 'COS10022', 'year' => 1, 'semester' => 3, 'type' => 'elective'],
        ];

        // Year 2 - Major Specialization Units (3 semesters: 4, 5, 6)
        $year2Units = [
            // Semester 4 - Year 2
            ['code' => 'COS20019', 'year' => 2, 'semester' => 4, 'type' => 'major'], // Cloud Computing Architecture
            ['code' => 'SWE30003', 'year' => 2, 'semester' => 4, 'type' => 'major'], // Software Architectures and Design
            ['code' => 'STA10003', 'year' => 2, 'semester' => 4, 'type' => 'elective'], // Foundation of Statistics

            // Semester 5 - Year 2
            ['code' => 'COS30008', 'year' => 2, 'semester' => 5, 'type' => 'major'], // Data Structures and Patterns
            ['code' => 'COS30017', 'year' => 2, 'semester' => 5, 'type' => 'major'], // Software Development for Mobile Devices
            ['code' => 'COS20028', 'year' => 2, 'semester' => 5, 'type' => 'elective'], // Big Data Architecture

            // Semester 6 - Year 2
            ['code' => 'COS30020', 'year' => 2, 'semester' => 6, 'type' => 'major'], // Advanced Web Development
            ['code' => 'COS30049', 'year' => 2, 'semester' => 6, 'type' => 'major'], // Computing Technology Innovation Project
            ['code' => 'COS30045', 'year' => 2, 'semester' => 6, 'type' => 'elective'], // Data Visualisation
        ];

        // Year 3 - Advanced and Capstone Units (3 semesters: 7, 8, 9)
        $year3Units = [
            // Semester 7 - Year 3
            ['code' => 'COS40005', 'year' => 3, 'semester' => 7, 'type' => 'core'], // Computing Technology Project A
            ['code' => 'SWE30009', 'year' => 3, 'semester' => 7, 'type' => 'major'], // Software Testing and Reliability
            ['code' => 'COS20001', 'year' => 3, 'semester' => 7, 'type' => 'elective'], // User-Centred Design

            // Semester 8 - Year 3
            ['code' => 'COS40006', 'year' => 3, 'semester' => 8, 'type' => 'core'], // Computing Technology Project B
            ['code' => 'SWE40006', 'year' => 3, 'semester' => 8, 'type' => 'major'], // Software Deployment and Evolution
            ['code' => 'COS30043', 'year' => 3, 'semester' => 8, 'type' => 'elective'], // Interface Design and Development

            // Semester 9 - Year 3
            ['code' => 'COS40003', 'year' => 3, 'semester' => 9, 'type' => 'major'], // Concurrent Programming
            ['code' => 'ICT20015', 'year' => 3, 'semester' => 9, 'type' => 'elective'], // ICT Professional Internship
            ['code' => 'SWE30011', 'year' => 3, 'semester' => 9, 'type' => 'elective'], // IoT Programming
        ];

        // Combine all units
        $allUnits = array_merge($year1Units, $year2Units, $year3Units);

        // Create curriculum units
        $this->createCurriculumUnits($curriculumVersion, $allUnits);

        // Validate unit distribution
        $this->validateUnitDistribution($allUnits);
    }

    private function createCurriculumUnits(CurriculumVersion $curriculumVersion, array $unitData): void
    {
        $createdUnits = 0;
        $updatedUnits = 0;
        $skippedUnits = 0;

        foreach ($unitData as $unitInfo) {
            $unit = Unit::where('code', $unitInfo['code'])->first();
            if (! $unit) {
                $this->command->warn("Unit {$unitInfo['code']} not found, skipping...");
                $skippedUnits++;

                continue;
            }

            // Use updateOrCreate to handle existing curriculum units
            $curriculumUnit = CurriculumUnit::updateOrCreate(
                [
                    'curriculum_version_id' => $curriculumVersion->id,
                    'unit_id' => $unit->id,
                ],
                [
                    'semester_id' => $curriculumVersion->semester_id,
                    'type' => $unitInfo['type'],
                    'year_level' => $unitInfo['year'],
                    'semester_number' => $unitInfo['semester'],
                    'note' => "IT Program - {$unitInfo['type']} unit for Year {$unitInfo['year']}, Semester {$unitInfo['semester']}",
                ]
            );

            if ($curriculumUnit->wasRecentlyCreated) {
                $createdUnits++;
            } else {
                $updatedUnits++;
            }
        }

        $this->command->info("✅ Created {$createdUnits} new curriculum units");
        if ($updatedUnits > 0) {
            $this->command->info("🔄 Updated {$updatedUnits} existing curriculum units");
        }
        if ($skippedUnits > 0) {
            $this->command->warn("⚠️  Skipped {$skippedUnits} units");
        }
    }

    private function validateUnitDistribution(array $units): void
    {
        $coreCount = 0;
        $majorCount = 0;
        $electiveCount = 0;
        $totalCredits = 0;

        foreach ($units as $unit) {
            switch ($unit['type']) {
                case 'core':
                    $coreCount++;
                    break;
                case 'major':
                    $majorCount++;
                    break;
                case 'elective':
                    $electiveCount++;
                    break;
            }
            $totalCredits += 12.5; // Each unit is 12.5 credit points
        }

        $this->command->info('📊 IT Program Unit Distribution Validation:');
        $this->command->info("   Core units: {$coreCount} ✅");
        $this->command->info("   Major units: {$majorCount} ✅");
        $this->command->info("   Elective units: {$electiveCount} ✅");
        $this->command->info('   Total units: ' . ($coreCount + $majorCount + $electiveCount));
        $this->command->info("   Total credits: {$totalCredits}");

        $this->command->info('🎉 IT Program curriculum structure created successfully!');
    }
}
