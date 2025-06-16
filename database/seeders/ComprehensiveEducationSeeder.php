<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Specialization;
use App\Models\Semester;
use App\Models\CurriculumVersion;
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

        // Handle foreign key checks based on database type
        $databaseType = DB::getDriverName();

        if ($databaseType === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } else if ($databaseType === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF;');
        }

        CurriculumUnit::truncate();
        CurriculumVersion::truncate();
        Specialization::truncate();
        Program::truncate();
        Unit::truncate();

        // Re-enable foreign key checks
        if ($databaseType === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else if ($databaseType === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON;');
        }
    }

    /**
     * Seed 9 semesters over 3 years (3 semesters per year).
     */
    private function seedSemesters(): void
    {
        $this->command->info('Seeding 9 semesters over 3 years...');

        $semesters = [];

        // Define semester types and their properties
        $semesterTypes = [
            1 => ['code' => 'SP', 'name' => 'Spring', 'start_month' => 1, 'end_month' => 5],
            2 => ['code' => 'SUM', 'name' => 'Summer', 'start_month' => 6, 'end_month' => 8],
            3 => ['code' => 'FAL', 'name' => 'Fall', 'start_month' => 8, 'end_month' => 12],
        ];

        for ($year = 1; $year <= 3; $year++) {
            $baseYear = 2024 + $year;

            for ($semester = 1; $semester <= 3; $semester++) {
                $semesterNumber = (($year - 1) * 3) + $semester;
                $semesterInfo = $semesterTypes[$semester];

                // Calculate dates based on semester type
                $startDate = match ($semester) {
                    1 => sprintf('%d-01-13', $baseYear), // Spring starts mid-January
                    2 => sprintf('%d-06-02', $baseYear), // Summer starts early June
                    3 => sprintf('%d-08-25', $baseYear), // Fall starts late August
                };

                $endDate = match ($semester) {
                    1 => sprintf('%d-05-10', $baseYear), // Spring ends early May
                    2 => sprintf('%d-08-15', $baseYear), // Summer ends mid-August
                    3 => sprintf('%d-12-14', $baseYear), // Fall ends mid-December
                };

                $enrollmentStartDate = match ($semester) {
                    1 => sprintf('%d-12-01 08:00:00', $baseYear - 1), // Spring enrollment starts December prior year
                    2 => sprintf('%d-04-01 08:00:00', $baseYear), // Summer enrollment starts April
                    3 => sprintf('%d-07-01 08:00:00', $baseYear), // Fall enrollment starts July
                };

                $enrollmentEndDate = match ($semester) {
                    1 => sprintf('%d-01-12 23:59:59', $baseYear), // Spring enrollment ends day before start
                    2 => sprintf('%d-06-01 23:59:59', $baseYear), // Summer enrollment ends day before start
                    3 => sprintf('%d-08-24 23:59:59', $baseYear), // Fall enrollment ends day before start
                };

                $semesters[] = [
                    'code' => sprintf('%s%d', $semesterInfo['code'], $baseYear),
                    'name' => sprintf('%s %d', $semesterInfo['name'], $baseYear),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'enrollment_start_date' => $enrollmentStartDate,
                    'enrollment_end_date' => $enrollmentEndDate,
                    'is_active' => $semesterNumber === 1, // First semester is active
                    'is_archived' => false,
                ];
            }
        }

        foreach ($semesters as $semester) {
            Semester::create($semester);
        }

        $this->command->info('9 semesters seeded.');
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
     * Each curriculum version includes exactly 27 curriculum units (9 semesters × 3 units).
     * Each semester: 2 mandatory units (core/major) + 1 elective unit.
     */
    private function seedCurriculumVersions(): void
    {
        $this->command->info('Seeding curriculum versions with 9-semester structure...');

        $specializations = Specialization::all();
        $semesters = Semester::all();
        $units = Unit::all();

        foreach ($specializations as $specialization) {
            for ($version = 1; $version <= 5; $version++) {
                $curriculumVersion = CurriculumVersion::create([
                    'program_id' => $specialization->program_id,
                    'specialization_id' => $specialization->id,
                    'version_code' => sprintf('%s-V%d-2025', $specialization->code, $version),
                    'semester_id' => $semesters->random()->id,
                    'notes' => sprintf(
                        'Version %d curriculum for %s specialization - 9 semesters over 3 years.',
                        $version,
                        $specialization->name
                    ),
                ]);

                // Add 27 curriculum units to each version (9 semesters × 3 units)
                $this->seedCurriculumUnits($curriculumVersion, $units, $semesters);
            }
        }

        $this->command->info('Curriculum versions and units seeded.');
    }

    /**
     * Add exactly 27 curriculum units for each curriculum version.
     * Distribute across 9 semesters with 3 units per semester.
     * Each semester: 2 mandatory units (core/major) + 1 elective unit.
     * Elective units are marked as optional in notes.
     */
    private function seedCurriculumUnits(
        CurriculumVersion $curriculumVersion,
        $units,
        $semesters
    ): void {
        $availableUnits = $units->shuffle();
        $unitIndex = 0;

        // Distribute 27 units across 9 semesters (3 units per semester)
        for ($semesterNumber = 1; $semesterNumber <= 9; $semesterNumber++) {
            for ($unitInSemester = 1; $unitInSemester <= 3; $unitInSemester++) {
                // Calculate year level (1-3) from semester number (1-9)
                $yearLevel = ceil($semesterNumber / 3);
                // Ensure we don't exceed available units
                if ($unitIndex >= $units->count()) {
                    $unitIndex = 0;
                    $availableUnits = $units->shuffle();
                }

                // Determine unit type: first 2 units are mandatory (core/major), 3rd is elective
                if ($unitInSemester <= 2) {
                    $type = rand(0, 1) ? 'core' : 'major';
                    $typeLabel = 'Mandatory';
                } else {
                    $type = 'elective';
                    $typeLabel = 'Elective (Can choose from any available unit)';
                }

                $note = sprintf(
                    'Year %d, Semester %d - Unit %d (%s)%s',
                    $yearLevel,
                    (($semesterNumber - 1) % 3) + 1,
                    $unitInSemester,
                    $typeLabel,
                    $type === 'elective' ? ' - ELECTIVE SLOT: Student can choose any unit from other specializations or programs' : ''
                );

                CurriculumUnit::create([
                    'curriculum_version_id' => $curriculumVersion->id,
                    'unit_id' => $availableUnits[$unitIndex]->id,
                    'type' => $type,
                    'year_level' => $yearLevel,
                    'note' => $note,
                ]);

                $unitIndex++;
            }
        }
    }
}
