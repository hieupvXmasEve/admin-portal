<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Specialization;
use App\Models\Semester;
use App\Models\CurriculumVersion;
use App\Models\CurriculumUnitType;
use App\Models\CurriculumUnit;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;

class ComprehensiveEducationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        $this->command->info('Starting Comprehensive Education seeding...');

        // Clear existing data if needed
        $this->clearExistingData();

        // Seed the structure in order
        $this->seedSemesters();
        $this->seedUnits();
        $this->seedCurriculumUnitTypes();
        $this->seedPrograms();
        $this->seedSpecializations();
        $this->seedCurriculumVersions();

        $this->command->info('Comprehensive Education seeding completed!');
    }

    /**
     * Clear existing data to ensure clean seeding.
     */
    private function clearExistingData(): void
    {
        $this->command->info('Clearing existing curriculum data...');

        // Disable foreign key checks to allow truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        CurriculumUnit::truncate();
        CurriculumVersion::truncate();
        Specialization::truncate();
        Program::truncate();
        Unit::truncate();
        CurriculumUnitType::truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Seed 9 semesters over 3 years.
     */
    private function seedSemesters(): void
    {
        $this->command->info('Seeding semesters...');

        $semesters = [];
        for ($year = 1; $year <= 3; $year++) {
            for ($semester = 1; $semester <= 3; $semester++) {
                $baseYear = 2024 + $year;
                $semesterNumber = (($year - 1) * 3) + $semester;

                $startMonth = match ($semester) {
                    1 => 2,  // February
                    2 => 6,  // June
                    3 => 10, // October
                };

                $endMonth = $startMonth + 3;
                if ($endMonth > 12) {
                    $endMonth -= 12;
                    $endYear = $baseYear + 1;
                } else {
                    $endYear = $baseYear;
                }

                $semesters[] = [
                    'code' => sprintf('%dS%d', $baseYear, $semester),
                    'name' => sprintf('%d Semester %d', $baseYear, $semester),
                    'start_date' => sprintf('%d-%02d-01', $baseYear, $startMonth),
                    'end_date' => sprintf('%d-%02d-30', $endYear, $endMonth),
                    'enrollment_start_date' => sprintf('%d-%02d-01', $baseYear, $startMonth - 1 > 0 ? $startMonth - 1 : 12),
                    'enrollment_end_date' => sprintf('%d-%02d-15', $baseYear, $startMonth),
                    'is_active' => $semesterNumber === 1,
                    'is_archived' => false,
                ];
            }
        }

        foreach ($semesters as $semester) {
            Semester::create($semester);
        }

        $this->command->info('Semesters seeded.');
    }

    /**
     * Generate 50 sample units.
     */
    private function seedUnits(): void
    {
        $this->command->info('Seeding 50 units...');

        $subjects = [
            'Computer Science',
            'Mathematics',
            'Physics',
            'Chemistry',
            'Biology',
            'Business',
            'Marketing',
            'Finance',
            'Economics',
            'Management',
            'Psychology',
            'Sociology',
            'Philosophy',
            'History',
            'Literature',
            'Engineering',
            'Architecture',
            'Design',
            'Art',
            'Music',
            'Communications',
            'Media Studies',
            'Journalism',
            'Public Relations',
            'Data Science',
            'Artificial Intelligence',
            'Machine Learning',
            'Cybersecurity',
            'Network Security',
            'Database Systems',
            'Software Engineering',
            'Web Development',
            'Mobile Development',
            'Statistics',
            'Research Methods',
            'Project Management',
            'Leadership',
            'Ethics',
            'Critical Thinking',
            'Problem Solving',
            'Innovation',
            'Entrepreneurship',
            'Digital Marketing',
            'International Business',
            'Supply Chain',
            'Operations Management',
            'Human Resources',
            'Organizational Behavior',
            'Strategic Planning',
            'Creative Writing',
            'Technical Writing'
        ];

        $levels = ['Foundation', 'Intermediate', 'Advanced', 'Specialized'];
        $creditPoints = [6, 12, 12.5, 15, 18];

        for ($i = 1; $i <= 50; $i++) {
            $subject = $subjects[array_rand($subjects)];
            $level = $levels[array_rand($levels)];
            $credits = $creditPoints[array_rand($creditPoints)];

            Unit::create([
                'code' => sprintf('UN%03d', $i),
                'name' => sprintf('%s %s', $level, $subject),
                'credit_points' => $credits,
            ]);
        }

        $this->command->info('Units seeded.');
    }

    /**
     * Seed curriculum unit types.
     */
    private function seedCurriculumUnitTypes(): void
    {
        $this->command->info('Seeding curriculum unit types...');

        $types = [
            ['name' => 'core'],
            ['name' => 'elective'],
            ['name' => 'major'],
        ];

        foreach ($types as $type) {
            CurriculumUnitType::create($type);
        }

        $this->command->info('Curriculum unit types seeded.');
    }

    /**
     * Seed programs.
     */
    private function seedPrograms(): void
    {
        $this->command->info('Seeding programs...');

        $programs = [
            [
                'name' => 'Bachelor of Computer Science',
                'code' => 'BCS',
                'description' => 'Comprehensive computer science program covering programming, algorithms, and system design.',
            ],
            [
                'name' => 'Bachelor of Business Administration',
                'code' => 'BBA',
                'description' => 'Strategic business management with focus on leadership, finance, and operations.',
            ],
            [
                'name' => 'Bachelor of Engineering',
                'code' => 'BE',
                'description' => 'Engineering fundamentals with focus on innovation, problem-solving, and technical design.',
            ],
            [
                'name' => 'Bachelor of Arts',
                'code' => 'BA',
                'description' => 'Liberal arts program emphasizing critical thinking, communication, and cultural awareness.',
            ],
            [
                'name' => 'Bachelor of Science',
                'code' => 'BS',
                'description' => 'Science-focused program covering natural sciences, mathematics, and research methods.',
            ],
        ];

        foreach ($programs as $program) {
            Program::create($program);
        }

        $this->command->info('Programs seeded.');
    }

    /**
     * Each program has 3 specializations.
     */
    private function seedSpecializations(): void
    {
        $this->command->info('Seeding specializations...');

        $specializationsByProgram = [
            'BCS' => [
                ['name' => 'Software Development', 'code' => 'BCS-SD'],
                ['name' => 'Data Science', 'code' => 'BCS-DS'],
                ['name' => 'Cybersecurity', 'code' => 'BCS-CS'],
            ],
            'BBA' => [
                ['name' => 'Marketing', 'code' => 'BBA-MK'],
                ['name' => 'Finance', 'code' => 'BBA-FN'],
                ['name' => 'Human Resources', 'code' => 'BBA-HR'],
            ],
            'BE' => [
                ['name' => 'Mechanical Engineering', 'code' => 'BE-ME'],
                ['name' => 'Electrical Engineering', 'code' => 'BE-EE'],
                ['name' => 'Civil Engineering', 'code' => 'BE-CE'],
            ],
            'BA' => [
                ['name' => 'Psychology', 'code' => 'BA-PS'],
                ['name' => 'Communications', 'code' => 'BA-CO'],
                ['name' => 'Philosophy', 'code' => 'BA-PH'],
            ],
            'BS' => [
                ['name' => 'Mathematics', 'code' => 'BS-MA'],
                ['name' => 'Physics', 'code' => 'BS-PH'],
                ['name' => 'Biology', 'code' => 'BS-BI'],
            ],
        ];

        $programs = Program::all();

        foreach ($programs as $program) {
            $specializations = $specializationsByProgram[$program->code] ?? [];

            foreach ($specializations as $specializationData) {
                Specialization::create([
                    'program_id' => $program->id,
                    'name' => $specializationData['name'],
                    'code' => $specializationData['code'],
                    'description' => sprintf(
                        'Specialized track in %s under %s program.',
                        $specializationData['name'],
                        $program->name
                    ),
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Specializations seeded.');
    }

    /**
     * Each specialization has 5 curriculum versions.
     * Each curriculum version includes exactly 15 curriculum units.
     * Units are distributed with 3 per semester pattern: 2 core/major + 1 elective.
     */
    private function seedCurriculumVersions(): void
    {
        $this->command->info('Seeding curriculum versions with units...');

        $specializations = Specialization::all();
        $semesters = Semester::all();
        $units = Unit::all();
        $unitTypes = CurriculumUnitType::all();

        $coreType = $unitTypes->where('name', 'core')->first();
        $electiveType = $unitTypes->where('name', 'elective')->first();
        $majorType = $unitTypes->where('name', 'major')->first();

        foreach ($specializations as $specialization) {
            for ($version = 1; $version <= 5; $version++) {
                $curriculumVersion = CurriculumVersion::create([
                    'program_id' => $specialization->program_id,
                    'specialization_id' => $specialization->id,
                    'version_code' => sprintf('%s-V%d-2025', $specialization->code, $version),
                    'semester_id' => $semesters->random()->id,
                    'notes' => sprintf(
                        'Version %d curriculum for %s specialization.',
                        $version,
                        $specialization->name
                    ),
                ]);

                // Add 15 curriculum units to each version
                $this->seedCurriculumUnits($curriculumVersion, $units, $semesters, $coreType, $electiveType, $majorType);
            }
        }

        $this->command->info('Curriculum versions and units seeded.');
    }

    /**
     * Add exactly 15 curriculum units for each curriculum version as specified.
     * Distribute across first 5 semesters with 3 units per semester.
     * Each semester: 2 core/major units + 1 elective unit.
     */
    private function seedCurriculumUnits(
        CurriculumVersion $curriculumVersion,
        $units,
        $semesters,
        $coreType,
        $electiveType,
        $majorType
    ): void {
        $availableUnits = $units->shuffle();
        $unitIndex = 0;

        // Distribute exactly 15 units across first 5 semesters (3 units per semester)
        for ($semesterOrder = 1; $semesterOrder <= 5; $semesterOrder++) {
            for ($unitInSemester = 1; $unitInSemester <= 3; $unitInSemester++) {
                // Ensure we don't exceed available units
                if ($unitIndex >= $units->count()) {
                    $unitIndex = 0;
                    $availableUnits = $units->shuffle();
                }

                $semester = $semesters->random();

                // Determine unit type: first 2 units are core/major, 3rd is elective
                if ($unitInSemester <= 2) {
                    $unitType = rand(0, 1) ? $coreType : $majorType;
                } else {
                    $unitType = $electiveType;
                }

                CurriculumUnit::create([
                    'curriculum_version_id' => $curriculumVersion->id,
                    'unit_id' => $availableUnits[$unitIndex]->id,
                    'semester_id' => $semester->id,
                    'unit_type_id' => $unitType->id,
                    'semester_order' => $semesterOrder,
                    'is_compulsory' => $unitType->name !== 'elective',
                    'note' => sprintf(
                        'Year %d, Semester %d, Unit %d (%s)',
                        ceil($semesterOrder / 3),
                        (($semesterOrder - 1) % 3) + 1,
                        $unitInSemester,
                        $unitType->name
                    ),
                ]);

                $unitIndex++;
            }
        }
    }
}
