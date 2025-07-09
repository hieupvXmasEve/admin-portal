<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Program;
use App\Models\Specialization;
use App\Models\Unit;
use App\Models\UnitPrerequisiteGroup;
use App\Models\UnitPrerequisiteCondition;
use App\Models\EquivalentUnit;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates programs, specializations, and units with prerequisites
     */
    public function run(): void
    {
        $this->command->info('🎓 Creating academic structure...');

        // Clean existing data
        $this->cleanExistingData();

        // Create programs
        $this->createPrograms();

        // Create specializations
        $this->createSpecializations();

        // Create units
        $this->createUnits();

        // Create prerequisite relationships
        $this->createPrerequisiteRelationships();

        // Create equivalent units
        $this->createEquivalentUnits();

        $this->command->info('✅ Academic structure created successfully!');
    }

    private function cleanExistingData(): void
    {
        EquivalentUnit::query()->delete();
        UnitPrerequisiteCondition::query()->delete();
        UnitPrerequisiteGroup::query()->delete();
        Unit::query()->delete();
        Specialization::query()->delete();
        Program::query()->delete();
    }

    private function createPrograms(): void
    {
        $programs = [
            [
                'id' => 1,
                'name' => 'Bachelor of Information Technology',
                'code' => 'BIT',
                'description' => 'A comprehensive program covering software development, cybersecurity, and emerging technologies.',
            ],
            [
                'id' => 2,
                'name' => 'Bachelor of Business',
                'code' => 'BBus',
                'description' => 'A dynamic business program preparing students for leadership roles in the modern economy.',
            ],
            [
                'id' => 3,
                'name' => 'Bachelor of Engineering',
                'code' => 'BEng',
                'description' => 'Engineering program focusing on practical applications and innovative solutions.',
            ],
        ];

        foreach ($programs as $program) {
            Program::create($program);
        }

        $this->command->info('📚 Created ' . count($programs) . ' programs');
    }

    private function createSpecializations(): void
    {
        $specializations = [
            // IT Specializations
            ['program_id' => 1, 'name' => 'Software Development', 'code' => 'SD', 'description' => 'Focus on software engineering and application development'],
            ['program_id' => 1, 'name' => 'Cybersecurity', 'code' => 'CS', 'description' => 'Specialization in information security and cyber defense'],
            ['program_id' => 1, 'name' => 'Data Science', 'code' => 'DS', 'description' => 'Analytics, machine learning, and big data technologies'],
            ['program_id' => 1, 'name' => 'Network Engineering', 'code' => 'NE', 'description' => 'Network infrastructure and telecommunications'],

            // Business Specializations
            ['program_id' => 2, 'name' => 'Marketing', 'code' => 'MKT', 'description' => 'Digital marketing and brand management'],
            ['program_id' => 2, 'name' => 'Finance', 'code' => 'FIN', 'description' => 'Corporate finance and investment analysis'],
            ['program_id' => 2, 'name' => 'Human Resources', 'code' => 'HR', 'description' => 'People management and organizational development'],
            ['program_id' => 2, 'name' => 'International Business', 'code' => 'IB', 'description' => 'Global business operations and strategy'],

            // Engineering Specializations
            ['program_id' => 3, 'name' => 'Civil Engineering', 'code' => 'CE', 'description' => 'Infrastructure and construction engineering'],
            ['program_id' => 3, 'name' => 'Mechanical Engineering', 'code' => 'ME', 'description' => 'Mechanical systems and manufacturing'],
            ['program_id' => 3, 'name' => 'Electrical Engineering', 'code' => 'EE', 'description' => 'Electrical systems and electronics'],
        ];

        foreach ($specializations as $specialization) {
            $specialization['is_active'] = true;
            Specialization::create($specialization);
        }

        $this->command->info('🎯 Created ' . count($specializations) . ' specializations');
    }

    private function createUnits(): void
    {
        $units = [
            // Foundation Units
            ['code' => 'COS10009', 'name' => 'Introduction to Programming', 'credit_points' => 12.5],
            ['code' => 'COS10011', 'name' => 'Creating Web Applications', 'credit_points' => 12.5],
            ['code' => 'COS10026', 'name' => 'Computing Technology Inquiry Project', 'credit_points' => 12.5],
            ['code' => 'TNE10005', 'name' => 'Network Engineering', 'credit_points' => 12.5],

            // Programming Units
            ['code' => 'COS20007', 'name' => 'Object Oriented Programming', 'credit_points' => 12.5],
            ['code' => 'COS20024', 'name' => 'Programming Data Structures', 'credit_points' => 12.5],
            ['code' => 'COS30019', 'name' => 'Introduction to Artificial Intelligence', 'credit_points' => 12.5],
            ['code' => 'COS30018', 'name' => 'Intelligent Systems', 'credit_points' => 12.5],
            ['code' => 'COS40007', 'name' => 'Artificial Intelligence for Engineering', 'credit_points' => 12.5],

            // Database Units
            ['code' => 'COS20019', 'name' => 'Cloud Computing Architecture', 'credit_points' => 12.5],
            ['code' => 'COS30020', 'name' => 'Advanced Database Systems', 'credit_points' => 12.5],
            ['code' => 'COS30043', 'name' => 'Interface Design and Development', 'credit_points' => 12.5],

            // Security Units
            ['code' => 'COS20014', 'name' => 'Information Security Management', 'credit_points' => 12.5],
            ['code' => 'COS30027', 'name' => 'Cybersecurity', 'credit_points' => 12.5],
            ['code' => 'COS40005', 'name' => 'Advanced Cybersecurity', 'credit_points' => 12.5],

            // Business Units
            ['code' => 'HRM10001', 'name' => 'Introduction to Management', 'credit_points' => 12.5],
            ['code' => 'MKT10001', 'name' => 'Marketing Principles', 'credit_points' => 12.5],
            ['code' => 'ACC10007', 'name' => 'Accounting for Business Decisions', 'credit_points' => 12.5],
            ['code' => 'ECO10004', 'name' => 'Economics for Business', 'credit_points' => 12.5],
            ['code' => 'FIN20001', 'name' => 'Corporate Finance', 'credit_points' => 12.5],
            ['code' => 'MKT20001', 'name' => 'Consumer Behaviour', 'credit_points' => 12.5],

            // Engineering Units
            ['code' => 'ENG10002', 'name' => 'Engineering Mathematics', 'credit_points' => 12.5],
            ['code' => 'ENG10003', 'name' => 'Engineering Physics', 'credit_points' => 12.5],
            ['code' => 'CIV20001', 'name' => 'Structural Analysis', 'credit_points' => 12.5],
            ['code' => 'MEC20001', 'name' => 'Thermodynamics', 'credit_points' => 12.5],
            ['code' => 'ELE20001', 'name' => 'Circuit Analysis', 'credit_points' => 12.5],

            // Mathematics Units
            ['code' => 'MAT10001', 'name' => 'Mathematics for Computing', 'credit_points' => 12.5],
            ['code' => 'MAT20001', 'name' => 'Statistics for Data Science', 'credit_points' => 12.5],
            ['code' => 'MAT30001', 'name' => 'Advanced Mathematics', 'credit_points' => 12.5],

            // English Units
            ['code' => 'ENG10001', 'name' => 'Academic English', 'credit_points' => 12.5],
            ['code' => 'ENG20001', 'name' => 'Business Communication', 'credit_points' => 12.5],
            ['code' => 'ENG30001', 'name' => 'Technical Writing', 'credit_points' => 12.5],

            // Capstone Units
            ['code' => 'COS40001', 'name' => 'Industry Experience', 'credit_points' => 25.0],
            ['code' => 'COS40002', 'name' => 'Computing Technology Project A', 'credit_points' => 12.5],
            ['code' => 'COS40003', 'name' => 'Computing Technology Project B', 'credit_points' => 12.5],
            ['code' => 'BUS40001', 'name' => 'Business Capstone Project', 'credit_points' => 25.0],
            ['code' => 'ENG40001', 'name' => 'Engineering Thesis', 'credit_points' => 25.0],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }

        $this->command->info('📖 Created ' . count($units) . ' units');
    }

    private function createPrerequisiteRelationships(): void
    {
        $prerequisites = [
            // Programming chain
            'COS20007' => ['COS10009'], // OOP requires Intro to Programming
            'COS20024' => ['COS10009'], // Data Structures requires Intro to Programming
            'COS30019' => ['COS20007'], // AI requires OOP
            'COS30018' => ['COS30019'], // Intelligent Systems requires Intro to AI
            'COS40007' => ['COS30018'], // AI for Engineering requires Intelligent Systems

            // Database chain
            'COS30020' => ['COS20024'], // Advanced DB requires Data Structures
            'COS30043' => ['COS20007'], // Interface Design requires OOP

            // Security chain
            'COS30027' => ['COS20014'], // Cybersecurity requires Info Security Management
            'COS40005' => ['COS30027'], // Advanced Cybersecurity requires Cybersecurity

            // Business chain
            'FIN20001' => ['ACC10007'], // Corporate Finance requires Accounting
            'MKT20001' => ['MKT10001'], // Consumer Behaviour requires Marketing Principles

            // Engineering chain
            'CIV20001' => ['ENG10002'], // Structural Analysis requires Engineering Math
            'MEC20001' => ['ENG10003'], // Thermodynamics requires Engineering Physics
            'ELE20001' => ['ENG10002'], // Circuit Analysis requires Engineering Math

            // Capstone requirements
            'COS40001' => ['COS30019', 'COS30020'], // Industry Experience requires AI and Advanced DB
            'COS40002' => ['COS30043'], // Project A requires Interface Design
            'COS40003' => ['COS40002'], // Project B requires Project A
            'BUS40001' => ['FIN20001', 'MKT20001'], // Business Capstone requires Finance and Marketing
            'ENG40001' => ['CIV20001', 'MEC20001', 'ELE20001'], // Engineering Thesis requires specialization units
        ];

        foreach ($prerequisites as $unitCode => $requiredUnitCodes) {
            $unit = Unit::where('code', $unitCode)->first();
            if (!$unit) continue;

            // Create prerequisite group for this unit
            $group = UnitPrerequisiteGroup::create([
                'unit_id' => $unit->id,
                'logic_operator' => 'AND',
                'description' => "Prerequisites for {$unit->code}",
            ]);

            // Add prerequisite conditions
            foreach ($requiredUnitCodes as $requiredUnitCode) {
                $requiredUnit = Unit::where('code', $requiredUnitCode)->first();
                if ($requiredUnit) {
                    UnitPrerequisiteCondition::create([
                        'group_id' => $group->id,
                        'type' => 'prerequisite',
                        'required_unit_id' => $requiredUnit->id,
                    ]);
                }
            }
        }

        $this->command->info('🔗 Created prerequisite relationships');
    }

    private function createEquivalentUnits(): void
    {
        // For now, we'll create some equivalent relationships between existing units
        // In a real scenario, these would be actual equivalent units in the system
        $equivalents = [
            // Create some cross-program equivalents
            'COS10009' => 'ENG10001', // Programming and English both foundational
            'MAT10001' => 'ENG10002', // Math and Physics both analytical
        ];

        foreach ($equivalents as $unitCode => $equivalentUnitCode) {
            $unit = Unit::where('code', $unitCode)->first();
            $equivalentUnit = Unit::where('code', $equivalentUnitCode)->first();

            if ($unit && $equivalentUnit) {
                EquivalentUnit::create([
                    'unit_id' => $unit->id,
                    'equivalent_unit_id' => $equivalentUnit->id,
                    'reason' => 'Cross-program equivalent unit',
                ]);
            }
        }

        $this->command->info('🔄 Created equivalent unit mappings');
    }
}
