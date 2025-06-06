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

class CurriculumSchemaSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        $this->command->info('Starting Curriculum Schema seeding...');

        // Seed unit types first
        $this->seedUnitTypes();

        // Seed programs and specializations
        $this->seedProgramsAndSpecializations();

        // Seed curriculum examples
        $this->seedCurriculumVersions();

        $this->command->info('Curriculum Schema seeding completed!');
    }

    /**
     * Seed curriculum unit types.
     */
    private function seedUnitTypes(): void
    {
        $types = [
            ['id' => 1, 'name' => 'core'],
            ['id' => 2, 'name' => 'elective'],
            ['id' => 3, 'name' => 'major'],
        ];

        foreach ($types as $type) {
            CurriculumUnitType::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }

        $this->command->info('Unit types seeded.');
    }

    /**
     * Seed programs and their specializations.
     */
    private function seedProgramsAndSpecializations(): void
    {
        $programsData = [
            [
                'program' => ['name' => 'Computer Science', 'code' => 'CS', 'description' => 'Comprehensive computer science program covering algorithms, programming, and system design.'],
                'specializations' => [
                    ['name' => 'Software Development', 'code' => 'CS-SD', 'description' => 'Focuses on application development, programming languages, and software engineering principles.'],
                    ['name' => 'Data Science', 'code' => 'CS-DS', 'description' => 'Covers machine learning, big data analytics, and statistical computing.'],
                    ['name' => 'Cybersecurity', 'code' => 'CS-CY', 'description' => 'Specializes in network security, ethical hacking, and information protection.'],
                ]
            ],
            [
                'program' => ['name' => 'Business Administration', 'code' => 'BA', 'description' => 'Strategic business management focusing on leadership, operations, and organizational development.'],
                'specializations' => [
                    ['name' => 'Marketing', 'code' => 'BA-MK', 'description' => 'Focuses on digital marketing, consumer behavior, and brand management.'],
                    ['name' => 'Finance', 'code' => 'BA-FN', 'description' => 'Covers corporate finance, investment analysis, and financial planning.'],
                    ['name' => 'Human Resources', 'code' => 'BA-HR', 'description' => 'Specializes in talent management, organizational behavior, and employment law.'],
                ]
            ],
            [
                'program' => ['name' => 'Engineering', 'code' => 'ENG', 'description' => 'Engineering fundamentals with focus on innovation, problem-solving, and technical design.'],
                'specializations' => [
                    ['name' => 'Mechanical Engineering', 'code' => 'EN-ME', 'description' => 'Focuses on mechanical systems, thermodynamics, and materials science.'],
                    ['name' => 'Electrical Engineering', 'code' => 'EN-EE', 'description' => 'Covers electrical systems, electronics, and power engineering.'],
                ]
            ],
        ];

        foreach ($programsData as $programData) {
            $program = Program::firstOrCreate(
                ['name' => $programData['program']['name']],
                $programData['program']
            );

            foreach ($programData['specializations'] as $specializationData) {
                Specialization::firstOrCreate(
                    ['program_id' => $program->id, 'code' => $specializationData['code']],
                    array_merge($specializationData, [
                        'program_id' => $program->id,
                        'is_active' => true,
                    ])
                );
            }
        }

        $this->command->info('Programs and specializations seeded.');
    }

    /**
     * Seed example curriculum versions with units.
     */
    private function seedCurriculumVersions(): void
    {
        // Get the first computer science program
        $csProgram = Program::where('name', 'Computer Science')->first();
        if (!$csProgram) return;

        // Get the software development specialization
        $sdSpecialization = $csProgram->specializations()->where('code', 'CS-SD')->first();
        if (!$sdSpecialization) return;

        // Get or create a semester
        $semester = Semester::first();
        if (!$semester) {
            $semester = Semester::create([
                'code' => '2025S1',
                'name' => '2025 Semester 1',
                'start_date' => '2025-03-01',
                'end_date' => '2025-06-30',
                'late_enrollment_date' => '2025-03-15',
                'withdrawal_deadline' => '2025-05-15',
                'is_active' => true,
                'is_current' => true,
            ]);
        }

        // Create a specialization-level curriculum version
        $specializationCurriculum = CurriculumVersion::firstOrCreate(
            [
                'program_id' => $csProgram->id,
                'specialization_id' => $sdSpecialization->id,
                'version_code' => 'CS-SD-2025-V1',
            ],
            [
                'semester_id' => $semester->id,
                'notes' => 'Software Development specialization curriculum for 2025.',
            ]
        );

        // Add some example units if any exist
        $units = Unit::limit(5)->get();
        $unitTypes = CurriculumUnitType::all();

        if ($units->count() > 0 && $unitTypes->count() > 0) {
            foreach ($units->take(3) as $index => $unit) {
                CurriculumUnit::firstOrCreate(
                    [
                        'curriculum_version_id' => $specializationCurriculum->id,
                        'unit_id' => $unit->id,
                    ],
                    [
                        'unit_type_id' => $unitTypes->random()->id,

                        'year_level' => ceil(($index + 1) / 2),
                        'semester_number' => (($index) % 2) + 1,
                        'note' => $index === 0 ? 'Foundation unit for all students' : null,
                    ]
                );
            }

            foreach ($units->skip(3)->take(2) as $index => $unit) {
                CurriculumUnit::firstOrCreate(
                    [
                        'curriculum_version_id' => $specializationCurriculum->id,
                        'unit_id' => $unit->id,
                    ],
                    [
                        'unit_type_id' => $unitTypes->where('name', 'major')->first()?->id,

                        'year_level' => ceil(($index + 4) / 2),
                        'semester_number' => (($index + 3) % 2) + 1,
                        'note' => 'Specialization-specific unit',
                    ]
                );
            }
        }

        $this->command->info('Curriculum versions and units seeded.');
    }
}
