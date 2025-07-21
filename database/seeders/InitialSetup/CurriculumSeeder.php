<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Program;
use App\Models\Specialization;
use App\Models\Unit;
use App\Models\CurriculumVersion;
use App\Models\CurriculumUnit;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates curriculum version for the AI specialization with proper unit distribution
     * Based on cs.txt: 3-year program, 300 credit points (24 units) across 9 semesters (3 per year)
     * 8 core units + 8 major units + 8 elective units
     */
    public function run(): void
    {
        $this->command->info('📚 Creating AI curriculum version...');

        // Clean existing data
        $this->cleanExistingData();

        // Create curriculum version for AI specialization
        $this->createAICurriculumVersion();

        $this->command->info('✅ AI curriculum version created successfully!');
    }

    private function cleanExistingData(): void
    {
        // Check if there are students or enrollments using curriculum versions
        $studentsCount = \App\Models\Student::count();
        $enrollmentsCount = \App\Models\Enrollment::whereNotNull('curriculum_version_id')->count();

        if ($studentsCount > 0 || $enrollmentsCount > 0) {
            $this->command->warn("⚠️  Found {$studentsCount} students and {$enrollmentsCount} enrollments. Skipping curriculum cleanup to preserve foreign key relationships.");
            $this->command->info("📝 Will update existing curriculum records instead of recreating them.");
            return;
        }

        try {
            CurriculumUnit::query()->delete();
            CurriculumVersion::query()->delete();
            $this->command->info("🧹 Cleaned existing curriculum data successfully.");
        } catch (\Exception $e) {
            $this->command->warn("⚠️  Could not clean existing curriculum data due to foreign key constraints. Will update existing records instead.");
            $this->command->info("Error: " . $e->getMessage());
        }
    }

    private function createAICurriculumVersion(): void
    {
        // Get the AI specialization
        $aiSpecialization = Specialization::where('code', 'AI')->first();

        if (!$aiSpecialization) {
            $this->command->error('AI specialization not found. Please run AcademicStructureSeeder first.');
            return;
        }

        // Create a default semester if none exists
        $defaultSemester = Semester::where('is_active', true)->first();

        // Create curriculum version
        $curriculumVersion = CurriculumVersion::updateOrCreate(
            [
                'program_id' => $aiSpecialization->program_id,
                'specialization_id' => $aiSpecialization->id,
                'version_code' => '2025.1',
            ],
            [
                'semester_id' => $defaultSemester->id,
                'notes' => 'AI Specialization Curriculum - 3 years, 300 credit points (24 units) across 9 semesters: 8 core + 8 major + 8 electives',
            ]
        );

        // Assign units to the AI curriculum
        $this->assignUnitsToAICurriculum($curriculumVersion);

        $this->command->info("📖 Created curriculum for AI specialization");
    }

    private function assignUnitsToAICurriculum(CurriculumVersion $curriculumVersion): void
    {
        // Year 1 - Foundation and Core Units (3 semesters: 1, 2, 3)
        $year1Units = [
            // Semester 1 - Year 1
            ['code' => 'COS10004', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'COS10009', 'year' => 1, 'semester' => 1, 'type' => 'core'],


            // Semester 2 - Year 1
            ['code' => 'TNE10006', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'COS10026', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'COS20007', 'year' => 1, 'semester' => 2, 'type' => 'core'],

            // Semester 3 - Year 1
            ['code' => 'COS20031', 'year' => 1, 'semester' => 3, 'type' => 'major'],
            ['code' => 'COS10025', 'year' => 1, 'semester' => 3, 'type' => 'core'],
            ['code' => 'COS10005', 'year' => 1, 'semester' => 3, 'type' => 'elective'], // Web Programming as foundation
        ];

        // Year 2 - Major Specialization Units (3 semesters: 4, 5, 6)
        $year2Units = [
            // Semester 4 - Year 2
            ['code' => 'COS30019', 'year' => 2, 'semester' => 4, 'type' => 'major'], // Introduction to AI
            ['code' => 'COS20019', 'year' => 2, 'semester' => 4, 'type' => 'major'], // Cloud Computing Architecture
            ['code' => 'COS10022', 'year' => 2, 'semester' => 4, 'type' => 'elective'], // Data Science Principles

            // Semester 5 - Year 2
            ['code' => 'SWE30003', 'year' => 2, 'semester' => 5, 'type' => 'major'], // Software Architectures and Design
            ['code' => 'STA10003', 'year' => 2, 'semester' => 5, 'type' => 'elective'], // Foundation of Statistics

            // Semester 6 - Year 2
            ['code' => 'COS30082', 'year' => 2, 'semester' => 6, 'type' => 'major'], // Applied Machine Learning
            ['code' => 'COS30049', 'year' => 2, 'semester' => 6, 'type' => 'major'], // Computing Technology Innovation Project
            ['code' => 'COS20015', 'year' => 2, 'semester' => 6, 'type' => 'elective'], // Fundamentals of Data Management
        ];

        // Year 3 - Advanced and Capstone Units (3 semesters: 7, 8, 9)
        $year3Units = [
            // Semester 7 - Year 3
            ['code' => 'COS40007', 'year' => 3, 'semester' => 7, 'type' => 'major'], // AI for Engineering
            ['code' => 'COS40005', 'year' => 3, 'semester' => 7, 'type' => 'core'], // Computing Technology Project A
            ['code' => 'COS30045', 'year' => 3, 'semester' => 7, 'type' => 'elective'], // Data Visualisation

            // Semester 8 - Year 3
            ['code' => 'COS40006', 'year' => 3, 'semester' => 8, 'type' => 'core'], // Computing Technology Project B
            ['code' => 'COS20028', 'year' => 3, 'semester' => 8, 'type' => 'elective'], // Big Data Architecture
            ['code' => 'COS30017', 'year' => 3, 'semester' => 8, 'type' => 'elective'], // Software Development for Mobile Devices

            // Semester 9 - Year 3
            ['code' => 'ICT20015', 'year' => 3, 'semester' => 9, 'type' => 'elective'], // ICT Professional Internship
            ['code' => 'COS30043', 'year' => 3, 'semester' => 9, 'type' => 'elective'], // Interface Design and Development
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
            if (!$unit) {
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
                    'note' => "AI Specialization - {$unitInfo['type']} unit for Year {$unitInfo['year']}, Semester {$unitInfo['semester']}",
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

        $this->command->info("📊 Unit Distribution Validation:");
        $this->command->info("   Core units: {$coreCount}/8 " . ($coreCount === 8 ? '✅' : '❌'));
        $this->command->info("   Major units: {$majorCount}/8 " . ($majorCount === 8 ? '✅' : '❌'));
        $this->command->info("   Elective units: {$electiveCount}/8 " . ($electiveCount === 8 ? '✅' : '❌'));
        $this->command->info("   Total units: " . ($coreCount + $majorCount + $electiveCount) . "/24");
        $this->command->info("   Total credits: {$totalCredits}/300 " . ($totalCredits == 300.0 ? '✅' : '❌'));

        if ($coreCount === 8 && $majorCount === 8 && $electiveCount === 8 && $totalCredits == 300.0) {
            $this->command->info("🎉 Curriculum structure matches cs.txt requirements perfectly!");
        } else {
            $this->command->warn("⚠️  Curriculum structure does not match expected requirements from cs.txt");
        }
    }
}
