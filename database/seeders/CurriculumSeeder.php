<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Models\Unit;
use App\Models\CurriculumUnit;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Campus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing curriculum data in proper order (foreign key constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Student::query()->delete();
        CurriculumUnit::query()->delete();
        CurriculumVersion::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Get the Bachelor of Computer Science program and CS specialization
        $program = Program::where('code', 'BCS')->first();
        $specialization = Specialization::where('code', 'BCS-CS')->first();
        $semester = Semester::where('code', 'SUM2025')->first();

        if (!$program || !$specialization || !$semester) {
            throw new \Exception('Program, Specialization, or Semester not found. Please run ProgramSeeder and SemesterSeeder first.');
        }

        // Create curriculum version for Bachelor of Computer Science CS specialization Summer 2025
        $curriculumVersion = CurriculumVersion::create([
            'program_id' => $program->id,
            'specialization_id' => $specialization->id,
            'version_code' => 'BCS-CS_V1_SUM25',
            'semester_id' => $semester->id,
            'notes' => 'Bachelor of Computer Science with CS specialization curriculum for Summer 2025',
        ]);

        // Add units to curriculum with year level and semester structure
        $this->addCurriculumUnits($curriculumVersion);

        // Add 60 students to this curriculum version
        $this->addStudentsToCurriculum($curriculumVersion);
    }

    private function addCurriculumUnits(CurriculumVersion $curriculumVersion): void
    {
        // Get the first available semester for this curriculum
        $semester = Semester::first();

        if (!$semester) {
            throw new \Exception('No semester found. Please run SemesterSeeder first.');
        }

        // Get all available units
        $allUnits = Unit::all();
        if ($allUnits->count() < 27) {
            throw new \Exception('Not enough units available. Need at least 27 units to create a 3-year curriculum.');
        }

        $unitStructure = [];
        $usedUnits = [];
        $unitIndex = 0;
        // Year 1 - 3, semester 1 - 9 semesters, 3 semesters each year, 3 subjects each (2 core, 1 elective)
        for ($year = 1; $year <= 3; $year++) {
            for ($semester = 1; $semester <= 3; $semester++) {
                // Calculate semester_number incrementally from 1 to 9
                $semesterNumber = ($year - 1) * 3 + $semester;

                // 2 core subjects
                for ($i = 0; $i < 2; $i++) {
                    if ($unitIndex < $allUnits->count()) {
                        $unit = $allUnits[$unitIndex];
                        $unitStructure[] = [
                            'code' => $unit->code,
                            'type' => 'core',
                            'year_level' => $year,
                            'semester_number' => $semesterNumber
                        ];
                        $unitIndex++;
                    }
                }

                // 1 elective subject
                if ($unitIndex < $allUnits->count()) {
                    $unit = $allUnits[$unitIndex];
                    $unitStructure[] = [
                        'code' => $unit->code,
                        'type' => 'elective',
                        'year_level' => $year,
                        'semester_number' => $semesterNumber
                    ];
                    $unitIndex++;
                }
            }
        }

        foreach ($unitStructure as $unitData) {
            $unit = Unit::where('code', $unitData['code'])->first();

            if ($unit) {
                CurriculumUnit::create([
                    'curriculum_version_id' => $curriculumVersion->id,
                    'unit_id' => $unit->id,
                    'type' => $unitData['type'],
                    'year_level' => $unitData['year_level'],
                    'semester_number' => $unitData['semester_number'],
                    'note' => "Year {$unitData['year_level']} Semester {$unitData['semester_number']} - " . ucfirst($unitData['type']) . " unit",
                ]);
            }
        }
    }

    private function addStudentsToCurriculum(CurriculumVersion $curriculumVersion): void
    {
        // Get the first available campus
        $campus = Campus::first();
        if (!$campus) {
            throw new \Exception('No campus found. Please run campus seeder first.');
        }

        // Clear existing students for this curriculum version to avoid duplicates
        Student::where('curriculum_version_id', $curriculumVersion->id)->delete();

        // Create 60 students for the BCS-CS_V1_SUM25 curriculum
        for ($i = 1; $i <= 60; $i++) {
            $studentNumber = str_pad((string) ($i + 100), 3, '0', STR_PAD_LEFT); // Start from 101 to avoid conflicts
            $studentId = "SWU2025{$studentNumber}";

            Student::create([
                'student_id' => $studentId,
                'full_name' => "Student CS {$i}",
                'email' => "student.cs.{$i}@swinburne.edu.au",
                'phone' => '0' . str_pad((string) (900000000 + $i), 9, '0', STR_PAD_LEFT),
                'date_of_birth' => fake()->dateTimeBetween('-25 years', '-18 years')->format('Y-m-d'),
                'gender' => fake()->randomElement(['male', 'female']),
                'nationality' => 'Vietnamese',
                'address' => fake()->address,
                'campus_id' => $campus->id,
                'program_id' => $curriculumVersion->program_id,
                'specialization_id' => $curriculumVersion->specialization_id,
                'curriculum_version_id' => $curriculumVersion->id,
                'admission_date' => '2025-01-15',
                'expected_graduation_date' => '2028-06-30',
                'high_school_name' => fake()->randomElement([
                    'Nguyen Hue High School',
                    'Le Loi High School',
                    'Tran Phu High School',
                    'Vo Thi Sau High School',
                    'Hung Vuong High School'
                ]),
                'high_school_graduation_year' => fake()->numberBetween(2020, 2024),
                'entrance_exam_score' => fake()->randomFloat(2, 6.0, 10.0),
                'status' => 'active',
                'oauth_provider' => 'google',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Created 60 students for curriculum version: {$curriculumVersion->version_code}");
    }
}
