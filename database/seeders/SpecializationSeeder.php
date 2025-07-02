<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Models\Unit;
use App\Models\CurriculumUnit;
use App\Models\UnitPrerequisite;
use App\Models\Semester;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSpecializations();
        $this->seedSpecializationUnits();
        $this->seedCurriculumWithSpecializations();
        $this->demonstrateCrossSpecializationUnits();
        $this->seedComplexPrerequisites();
        $this->seedStudents();
    }

    private function seedSpecializations(): void
    {
        $specializationsData = [
            'IT' => [
                ['name' => 'Software Development', 'code' => 'IT-SD', 'description' => 'Focus on software engineering, web development, and application development'],
                ['name' => 'Cybersecurity', 'code' => 'IT-CS', 'description' => 'Focus on information security, ethical hacking, and cyber defense'],
                ['name' => 'Data Science', 'code' => 'IT-DS', 'description' => 'Focus on data analysis, machine learning, and big data technologies'],
                ['name' => 'Network Engineering', 'code' => 'IT-NE', 'description' => 'Focus on network infrastructure, telecommunications, and system administration'],
            ],
            'Business' => [
                ['name' => 'Marketing', 'code' => 'BUS-MKT', 'description' => 'Focus on marketing strategies, consumer behavior, and digital marketing'],
                ['name' => 'Finance', 'code' => 'BUS-FIN', 'description' => 'Focus on financial management, investment analysis, and corporate finance'],
                ['name' => 'Human Resources', 'code' => 'BUS-HR', 'description' => 'Focus on people management, organizational behavior, and talent acquisition'],
                ['name' => 'International Business', 'code' => 'BUS-IB', 'description' => 'Focus on global markets, international trade, and cross-cultural management'],
            ],
        ];

        foreach ($specializationsData as $programName => $specializations) {
            $program = Program::where('name', $programName)->first();
            if ($program) {
                foreach ($specializations as $spec) {
                    Specialization::create([
                        'program_id' => $program->id,
                        'name' => $spec['name'],
                        'code' => $spec['code'],
                        'description' => $spec['description'],
                    ]);
                }
            }
        }
    }

    private function seedSpecializationUnits(): void
    {
        // Create specialized units that will be used across different specializations
        $specializationUnits = [
            // Software Development units
            ['code' => 'SD301', 'name' => 'Advanced Software Engineering', 'credit_points' => 4.0],
            ['code' => 'SD302', 'name' => 'Mobile Application Development', 'credit_points' => 4.0],
            ['code' => 'SD303', 'name' => 'Web Development Frameworks', 'credit_points' => 4.0],
            ['code' => 'SD401', 'name' => 'Software Architecture and Design Patterns', 'credit_points' => 4.0],

            // Cybersecurity units
            ['code' => 'CS301', 'name' => 'Network Security', 'credit_points' => 4.0],
            ['code' => 'CS302', 'name' => 'Ethical Hacking and Penetration Testing', 'credit_points' => 4.0],
            ['code' => 'CS303', 'name' => 'Cryptography and Information Security', 'credit_points' => 4.0],
            ['code' => 'CS401', 'name' => 'Advanced Cybersecurity Management', 'credit_points' => 4.0],

            // Data Science units
            ['code' => 'DS301', 'name' => 'Machine Learning Fundamentals', 'credit_points' => 4.0],
            ['code' => 'DS302', 'name' => 'Big Data Analytics', 'credit_points' => 4.0],
            ['code' => 'DS303', 'name' => 'Statistical Modeling', 'credit_points' => 4.0],
            ['code' => 'DS401', 'name' => 'Deep Learning and Neural Networks', 'credit_points' => 4.0],

            // Network Engineering units
            ['code' => 'NE301', 'name' => 'Network Infrastructure Design', 'credit_points' => 4.0],
            ['code' => 'NE302', 'name' => 'Systems Administration', 'credit_points' => 4.0],
            ['code' => 'NE303', 'name' => 'Cloud Computing and Virtualization', 'credit_points' => 4.0],

            // Business specialization units
            ['code' => 'MKT301', 'name' => 'Digital Marketing and Social Media', 'credit_points' => 3.0],
            ['code' => 'MKT302', 'name' => 'Consumer Psychology', 'credit_points' => 3.0],
            ['code' => 'FIN301', 'name' => 'Investment Analysis', 'credit_points' => 3.0],
            ['code' => 'FIN302', 'name' => 'Corporate Finance', 'credit_points' => 3.0],
            ['code' => 'HR301', 'name' => 'Organizational Behavior', 'credit_points' => 3.0],
            ['code' => 'IB301', 'name' => 'International Trade', 'credit_points' => 3.0],

            // Cross-specialization units (will be core in one, elective in others)
            ['code' => 'IT301', 'name' => 'Database Management Systems', 'credit_points' => 4.0],
            ['code' => 'IT302', 'name' => 'System Analysis and Design', 'credit_points' => 4.0],
            ['code' => 'IT303', 'name' => 'Project Management for IT', 'credit_points' => 3.0],
            ['code' => 'MATH301', 'name' => 'Advanced Statistics', 'credit_points' => 3.0],
        ];

        foreach ($specializationUnits as $unitData) {
            Unit::firstOrCreate(
                ['code' => $unitData['code']],
                [
                    'name' => $unitData['name'],
                    'credit_points' => $unitData['credit_points'],
                ]
            );
        }
    }

    private function seedCurriculumWithSpecializations(): void
    {
        $semester = Semester::first();

        // Create program-level curricula (common units for all specializations)
        foreach (Program::with('specializations')->get() as $program) {
            if ($program->specializations->count() === 0) continue;

            // Create program-level curriculum (common units)
            $programCurriculum = CurriculumVersion::create([
                'program_id' => $program->id,
                'version_code' => $program->code . '_COM_V1.0',
                'semester_id' => $semester?->id,
                'notes' => 'Common curriculum for all ' . $program->name . ' specializations',
            ]);

            $this->addCommonUnits($programCurriculum, $program->code);

            // Create specialization-specific curricula
            foreach ($program->specializations as $specialization) {
                $specCurriculum = CurriculumVersion::create([
                    'program_id' => $program->id,
                    'specialization_id' => $specialization->id,
                    'version_code' => $specialization->code . '_V1.0',
                    'semester_id' => $semester?->id,
                    'notes' => 'Specialization-specific curriculum for ' . $specialization->name,
                ]);

                $this->addSpecializationUnits($specCurriculum, $specialization);
            }
        }
    }

    private function addCommonUnits(CurriculumVersion $curriculum, string $programCode): void
    {
        $commonUnits = [
            'IT' => [
                ['code' => 'CS101', 'type' => 'core', 'year_level' => 1, 'semester_number' => 1],
                ['code' => 'CS102', 'type' => 'core', 'year_level' => 1, 'semester_number' => 2],
                ['code' => 'CS201', 'type' => 'core', 'year_level' => 2, 'semester_number' => 1],
                ['code' => 'IT301', 'type' => 'core', 'year_level' => 3, 'semester_number' => 1], // Database - core for all IT
            ],
            'BUS' => [
                ['code' => 'BUS101', 'type' => 'core', 'year_level' => 1, 'semester_number' => 1],
                ['code' => 'BUS201', 'type' => 'core', 'year_level' => 2, 'semester_number' => 1],
                ['code' => 'BUS301', 'type' => 'core', 'year_level' => 3, 'semester_number' => 1],
            ],
        ];

        if (isset($commonUnits[$programCode])) {
            foreach ($commonUnits[$programCode] as $unitData) {
                $unit = Unit::where('code', $unitData['code'])->first();
                if ($unit) {
                    CurriculumUnit::create([
                        'curriculum_version_id' => $curriculum->id,
                        'unit_id' => $unit->id,
                        'type' => $unitData['type'],
                        'unit_scope' => 'common',
                        'is_required' => true,
                        'year_level' => $unitData['year_level'],
                        'semester_number' => $unitData['semester_number'],
                    ]);
                }
            }
        }
    }

    private function addSpecializationUnits(CurriculumVersion $curriculum, Specialization $specialization): void
    {
        $specializationCode = $specialization->code;

        // Define core units for each specialization
        $specUnits = [
            'IT-SD' => [
                ['code' => 'SD301', 'type' => 'major', 'year_level' => 3, 'semester_number' => 1],
                ['code' => 'SD302', 'type' => 'major', 'year_level' => 3, 'semester_number' => 2],
                ['code' => 'SD303', 'type' => 'major', 'year_level' => 3, 'semester_number' => 2],
                ['code' => 'SD401', 'type' => 'major', 'year_level' => 4, 'semester_number' => 1],
                // System Analysis is CORE for Software Development
                ['code' => 'IT302', 'type' => 'core', 'year_level' => 3, 'semester_number' => 1],
            ],
            'IT-CS' => [
                ['code' => 'CS301', 'type' => 'major', 'year_level' => 3, 'semester_number' => 1],
                ['code' => 'CS302', 'type' => 'major', 'year_level' => 3, 'semester_number' => 2],
                ['code' => 'CS303', 'type' => 'major', 'year_level' => 3, 'semester_number' => 2],
                ['code' => 'CS401', 'type' => 'major', 'year_level' => 4, 'semester_number' => 1],
                // Statistics is CORE for Cybersecurity (for risk analysis)
                ['code' => 'MATH301', 'type' => 'core', 'year_level' => 3, 'semester_number' => 1],
            ],
            'IT-DS' => [
                ['code' => 'DS301', 'type' => 'major', 'year_level' => 3, 'semester_number' => 1],
                ['code' => 'DS302', 'type' => 'major', 'year_level' => 3, 'semester_number' => 2],
                ['code' => 'DS303', 'type' => 'major', 'year_level' => 3, 'semester_number' => 2],
                ['code' => 'DS401', 'type' => 'major', 'year_level' => 4, 'semester_number' => 1],
                // Statistics is CORE for Data Science
                ['code' => 'MATH301', 'type' => 'core', 'year_level' => 2, 'semester_number' => 2],
            ],
            'IT-NE' => [
                ['code' => 'NE301', 'type' => 'major', 'year_level' => 3, 'semester_number' => 1],
                ['code' => 'NE302', 'type' => 'major', 'year_level' => 3, 'semester_number' => 2],
                ['code' => 'NE303', 'type' => 'major', 'year_level' => 4, 'semester_number' => 1],
                // Network Security is CORE for Network Engineering
                ['code' => 'CS301', 'type' => 'core', 'year_level' => 3, 'semester_number' => 2],
            ],
        ];

        if (isset($specUnits[$specializationCode])) {
            foreach ($specUnits[$specializationCode] as $unitData) {
                $unit = Unit::where('code', $unitData['code'])->first();
                if ($unit) {
                    CurriculumUnit::create([
                        'curriculum_version_id' => $curriculum->id,
                        'unit_id' => $unit->id,
                        'type' => $unitData['type'],
                        'unit_scope' => 'specialization_specific',
                        'is_required' => true,
                        'year_level' => $unitData['year_level'],
                        'semester_number' => $unitData['semester_number'],
                    ]);
                }
            }
        }
    }

    private function demonstrateCrossSpecializationUnits(): void
    {
        // Add units that are electives in some specializations but core in others
        $itProgram = Program::where('name', 'IT')->first();
        if (!$itProgram) return;

        $itSpecializations = Specialization::where('program_id', $itProgram->id)->get();

        foreach ($itSpecializations as $specialization) {
            $curriculum = $specialization->curriculumVersions()->first();
            if (!$curriculum) continue;

            // Add cross-specialization electives
            switch ($specialization->code) {
                case 'IT-SD':
                    // For Software Development, add Cybersecurity as elective
                    $this->addElectiveUnit($curriculum, 'CS301', 4, 1); // Network Security as elective
                    $this->addElectiveUnit($curriculum, 'DS301', 4, 2); // Machine Learning as elective
                    break;

                case 'IT-CS':
                    // For Cybersecurity, add Software Development units as electives
                    $this->addElectiveUnit($curriculum, 'SD301', 4, 1); // Software Engineering as elective
                    $this->addElectiveUnit($curriculum, 'IT302', 4, 2); // System Analysis as elective (was core in SD)
                    break;

                case 'IT-DS':
                    // For Data Science, add other specialization units as electives
                    $this->addElectiveUnit($curriculum, 'CS302', 4, 1); // Ethical Hacking as elective
                    $this->addElectiveUnit($curriculum, 'SD302', 4, 2); // Mobile Development as elective
                    break;

                case 'IT-NE':
                    // For Network Engineering, add various electives
                    $this->addElectiveUnit($curriculum, 'IT302', 4, 1); // System Analysis as elective
                    $this->addElectiveUnit($curriculum, 'DS302', 4, 2); // Big Data as elective
                    break;
            }
        }
    }

    private function addElectiveUnit(CurriculumVersion $curriculum, string $unitCode, int $yearLevel, int $semester): void
    {
        $unit = Unit::where('code', $unitCode)->first();
        if ($unit) {
            CurriculumUnit::create([
                'curriculum_version_id' => $curriculum->id,
                'unit_id' => $unit->id,
                'type' => 'elective',
                'unit_scope' => 'cross_program',
                'is_required' => false,
                'year_level' => $yearLevel,
                'semester_number' => $semester,
                'minimum_grade' => 2.0, // Minimum grade for electives
            ]);
        }
    }

    private function seedComplexPrerequisites(): void
    {
        // Add complex prerequisites that apply regardless of unit role
        $prerequisites = [
            // Advanced units require foundational units
            ['unit_code' => 'SD301', 'required_unit_code' => 'CS201', 'type' => 'prerequisite'],
            ['unit_code' => 'CS301', 'required_unit_code' => 'CS201', 'type' => 'prerequisite'],
            ['unit_code' => 'DS301', 'required_unit_code' => 'MATH301', 'type' => 'prerequisite'],
            ['unit_code' => 'DS401', 'required_unit_code' => 'DS301', 'type' => 'prerequisite'],

            // Database is prerequisite for many advanced units
            ['unit_code' => 'SD302', 'required_unit_code' => 'IT301', 'type' => 'prerequisite'],
            ['unit_code' => 'DS302', 'required_unit_code' => 'IT301', 'type' => 'prerequisite'],

            // System Analysis requires multiple prerequisites
            ['unit_code' => 'IT302', 'required_unit_code' => 'CS201', 'type' => 'prerequisite'],
            ['unit_code' => 'IT302', 'required_unit_code' => 'IT301', 'type' => 'co_requisite'],

            // Statistics has assumed knowledge requirements
            ['unit_code' => 'MATH301', 'required_unit_code' => 'CS101', 'type' => 'assumed_knowledge'],
        ];

        // Group prerequisites by unit_id to create groups
        $groupedPrerequisites = [];
        foreach ($prerequisites as $prereq) {
            $unit = Unit::where('code', $prereq['unit_code'])->first();
            $requiredUnit = Unit::where('code', $prereq['required_unit_code'])->first();

            if ($unit && $requiredUnit) {
                if (!isset($groupedPrerequisites[$unit->id])) {
                    $groupedPrerequisites[$unit->id] = [];
                }
                $groupedPrerequisites[$unit->id][] = [
                    'type' => $prereq['type'],
                    'required_unit_id' => $requiredUnit->id,
                ];
            }
        }

        // Create prerequisite groups and conditions
        foreach ($groupedPrerequisites as $unitId => $unitPrerequisites) {
            // Create a prerequisite group for this unit
            $group = \App\Models\UnitPrerequisiteGroup::create([
                'unit_id' => $unitId,
                'logic_operator' => 'AND',
                'description' => 'Complex prerequisites seeded for specializations',
            ]);

            // Create conditions for each prerequisite
            foreach ($unitPrerequisites as $prerequisite) {
                \App\Models\UnitPrerequisiteCondition::create([
                    'group_id' => $group->id,
                    'type' => $prerequisite['type'],
                    'required_unit_id' => $prerequisite['required_unit_id'],
                ]);
            }
        }
    }

    private function seedStudents(): void
    {
        // Clear existing students
        \App\Models\Student::query()->delete();

        // Get required data
        $campus = \App\Models\Campus::first();
        if (!$campus) {
            throw new \Exception('No campus found. Please run campus seeder first.');
        }

        // Create students for each specialization's curriculum
        foreach (\App\Models\CurriculumVersion::with(['program', 'specialization'])->get() as $curriculum) {
            if (!$curriculum->specialization) continue; // Skip program-level curricula

            // Create 20 students per specialization
            for ($i = 1; $i <= 20; $i++) {
                $specializationCode = strtoupper(str_replace('-', '', $curriculum->specialization->code));
                $studentNumber = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $studentId = "SWU{$specializationCode}25{$studentNumber}";

                \App\Models\Student::create([
                    'student_id' => $studentId,
                    'full_name' => "Student {$curriculum->specialization->name} {$i}",
                    'email' => strtolower("student.{$specializationCode}.{$i}@swinburne.edu.au"),
                    'phone' => '0' . str_pad((string) (900000000 + $i + ($curriculum->id * 100)), 9, '0', STR_PAD_LEFT),
                    'date_of_birth' => fake()->dateTimeBetween('-25 years', '-18 years')->format('Y-m-d'),
                    'gender' => fake()->randomElement(['male', 'female']),
                    'nationality' => 'Vietnamese',
                    'address' => fake()->address,
                    'campus_id' => $campus->id,
                    'program_id' => $curriculum->program_id,
                    'specialization_id' => $curriculum->specialization_id,
                    'curriculum_version_id' => $curriculum->id,
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
        }

        $this->command->info('Created students for all specialization curricula');
    }
}
