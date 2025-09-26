<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Program;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\UnitPrerequisiteCondition;
use App\Models\UnitPrerequisiteGroup;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates one program (Information Technology) with units and syllabus templates
     */
    public function run(): void
    {
        $this->command->info('🎓 Creating academic structure for IT program...');

        // Clean existing data
        $this->cleanExistingData();

        // Create program
        $this->createProgram();

        // Create units
        $this->createUnits();

        // Create syllabus templates for each unit
        $this->createSyllabusTemplates();

        // Create prerequisite relationships
        $this->createPrerequisiteRelationships();

        $this->command->info('✅ Academic structure created successfully!');
    }

    private function cleanExistingData(): void
    {
        // Check if there are students or other dependencies
        $studentsCount = \App\Models\Student::count();
        $enrollmentsCount = \App\Models\Enrollment::whereNotNull('curriculum_version_id')->count();

        if ($studentsCount > 0 || $enrollmentsCount > 0) {
            $this->command->warn("⚠️  Found {$studentsCount} students and {$enrollmentsCount} enrollments. Skipping data cleanup to preserve foreign key relationships.");
            $this->command->info('📝 Will update existing records instead of recreating them.');

            return;
        }

        // Only clean if safe to do so
        try {
            SyllabusTemplate::query()->delete();
            UnitPrerequisiteCondition::query()->delete();
            UnitPrerequisiteGroup::query()->delete();
            Unit::query()->delete();
            Program::query()->delete();
            $this->command->info('🧹 Cleaned existing data successfully.');
        } catch (\Exception $e) {
            $this->command->warn('⚠️  Could not clean existing data due to foreign key constraints. Will update existing records instead.');
            $this->command->info('Error: ' . $e->getMessage());
        }
    }

    private function createProgram(): void
    {
        // Information Technology Program
        $itProgram = Program::updateOrCreate(
            ['code' => 'IT'],
            [
                'id' => 1,
                'name' => 'Information Technology',
                'description' => 'A comprehensive program designed to equip students with knowledge and skills in information technology, software development, and digital systems for careers in the IT industry.',
            ]
        );

        $this->command->info('📚 Created/Updated Information Technology program');
    }


    private function createUnits(): void
    {
        $units = [
            // Core IT Units
            ['code' => 'COS10004', 'name' => 'Computer Systems', 'credit_points' => 12.5],
            ['code' => 'COS10009', 'name' => 'Introduction to Programming', 'credit_points' => 12.5],
            ['code' => 'COS10026', 'name' => 'Computing Technology Inquiry Project', 'credit_points' => 12.5],
            ['code' => 'COS20007', 'name' => 'Object Oriented Programming', 'credit_points' => 12.5],
            ['code' => 'COS20031', 'name' => 'Computing Technology Design Project', 'credit_points' => 12.5],
            ['code' => 'COS40005', 'name' => 'Computing Technology Project A', 'credit_points' => 12.5],
            ['code' => 'COS40006', 'name' => 'Computing Technology Project B', 'credit_points' => 12.5],
            ['code' => 'TNE10006', 'name' => 'Networks and Switching', 'credit_points' => 12.5],

            // Software Development Units
            ['code' => 'COS10005', 'name' => 'Web Programming', 'credit_points' => 12.5],
            ['code' => 'COS20015', 'name' => 'Fundamentals of Data Management', 'credit_points' => 12.5],
            ['code' => 'COS30008', 'name' => 'Data Structures and Patterns', 'credit_points' => 12.5],
            ['code' => 'COS30017', 'name' => 'Software Development for Mobile Devices', 'credit_points' => 12.5],
            ['code' => 'COS30020', 'name' => 'Advanced Web Development', 'credit_points' => 12.5],
            ['code' => 'SWE30003', 'name' => 'Software Architectures and Design', 'credit_points' => 12.5],
            ['code' => 'SWE30009', 'name' => 'Software Testing and Reliability', 'credit_points' => 12.5],
            ['code' => 'SWE40006', 'name' => 'Software Deployment and Evolution', 'credit_points' => 12.5],

            // Data Science Units
            ['code' => 'COS10022', 'name' => 'Data Science Principles', 'credit_points' => 12.5],
            ['code' => 'COS20028', 'name' => 'Big Data Architecture and Application', 'credit_points' => 12.5],
            ['code' => 'COS30045', 'name' => 'Data Visualisation', 'credit_points' => 12.5],
            ['code' => 'STA10003', 'name' => 'Foundation of Statistics', 'credit_points' => 12.5],

            // Security Units
            ['code' => 'COS20030', 'name' => 'Malware Analysis', 'credit_points' => 12.5],
            ['code' => 'COS30015', 'name' => 'IT Security', 'credit_points' => 12.5],
            ['code' => 'TNE20003', 'name' => 'Internet and Cybersecurity for Engineering Applications', 'credit_points' => 12.5],
            ['code' => 'TNE30009', 'name' => 'Network Security and Resilience', 'credit_points' => 12.5],

            // Network and Infrastructure Units
            ['code' => 'TNE10005', 'name' => 'Network Administration', 'credit_points' => 12.5],
            ['code' => 'COS20019', 'name' => 'Cloud Computing Architecture', 'credit_points' => 12.5],
            ['code' => 'SWE30011', 'name' => 'IoT Programming', 'credit_points' => 12.5],

            // User Experience and Design Units
            ['code' => 'COS20001', 'name' => 'User-Centred Design', 'credit_points' => 12.5],
            ['code' => 'COS30043', 'name' => 'Interface Design and Development', 'credit_points' => 12.5],

            // Advanced Programming Units
            ['code' => 'COS40003', 'name' => 'Concurrent Programming', 'credit_points' => 12.5],
            ['code' => 'COS30049', 'name' => 'Computing Technology Innovation Project', 'credit_points' => 12.5],

            // Professional Development Units
            ['code' => 'ICT20015', 'name' => 'ICT Professional Internship', 'credit_points' => 12.5],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']], // Find by code
                [
                    'name' => $unit['name'],
                    'credit_points' => $unit['credit_points'],
                ]
            );
        }

        $this->command->info('📖 Created ' . count($units) . ' units');
    }

    private function createSyllabusTemplates(): void
    {
        $units = Unit::all();

        foreach ($units as $unit) {
            SyllabusTemplate::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'version' => '1.0',
                ],
                [
                    'title' => "Syllabus for {$unit->name}",
                    'description' => "This syllabus covers the fundamental concepts and practical applications of {$unit->name}. Students will develop both theoretical understanding and hands-on skills through a combination of lectures, tutorials, and practical exercises.",
                    'total_hours' => 150,
                    'total_sessions' => 12,
                    'learning_outcomes' => [
                        "Understand the core concepts and principles of {$unit->name}",
                        "Apply theoretical knowledge to practical scenarios",
                        "Develop critical thinking and problem-solving skills",
                        "Demonstrate proficiency in relevant tools and technologies",
                        "Communicate effectively about technical concepts"
                    ],
                    'grading_criteria' => [
                        'Assignments' => 40,
                        'Mid-term Exam' => 25,
                        'Final Exam' => 30,
                        'Participation' => 5
                    ],
                    'required_materials' => [
                        'Textbook: Core Concepts in Information Technology',
                        'Software: Development environment and tools',
                        'Hardware: Computer with internet access'
                    ],
                    'assessment_policy' => 'Students must achieve a minimum of 50% overall to pass this unit. Late submissions will incur penalties as per university policy.',
                    'applicable_program_id' => 1, // IT Program
                    'delivery_mode' => 'Blended',
                    'is_default' => true,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('📋 Created syllabus templates for ' . count($units) . ' units');
    }

    private function createPrerequisiteRelationships(): void
    {
        $prerequisites = [
            // Programming progression
            'COS20007' => ['COS10009'], // OOP requires Intro to Programming
            'COS30008' => ['COS20007'], // Data Structures requires OOP
            'COS30017' => ['COS20007'], // Mobile Development requires OOP
            'COS30020' => ['COS10005'], // Advanced Web Development requires Web Programming
            'COS40003' => ['COS30008'], // Concurrent Programming requires Data Structures

            // Project progression
            'COS20031' => ['COS10026'], // Design Project requires Inquiry Project
            'COS40005' => ['COS20031'], // Project A requires Design Project
            'COS40006' => ['COS40005'], // Project B requires Project A
            'COS30049' => ['COS20031'], // Innovation Project requires Design Project

            // Software Architecture progression
            'SWE30003' => ['COS20007'], // Software Architecture requires OOP
            'SWE30009' => ['SWE30003'], // Software Testing requires Software Architecture
            'SWE40006' => ['SWE30003'], // Software Deployment requires Software Architecture

            // Data and Analytics progression
            'COS20015' => ['COS10009'], // Data Management requires Intro to Programming
            'COS20028' => ['COS10022'], // Big Data requires Data Science Principles
            'COS30045' => ['STA10003'], // Data Visualisation requires Statistics

            // Security progression
            'COS30015' => ['COS10004'], // IT Security requires Computer Systems
            'TNE20003' => ['TNE10006'], // Internet Security requires Networks and Switching
            'TNE30009' => ['TNE20003'], // Network Security requires Internet Security
            'COS20030' => ['COS30015'], // Malware Analysis requires IT Security

            // Network progression
            'TNE10005' => ['TNE10006'], // Network Administration requires Networks and Switching
            'COS20019' => ['TNE10006'], // Cloud Computing requires Networks and Switching

            // IoT and Advanced Programming
            'SWE30011' => ['COS30017'], // IoT Programming requires Mobile Development

            // User Experience
            'COS30043' => ['COS20001'], // Interface Design requires User-Centred Design

            // Professional Development
            'ICT20015' => ['COS20031'], // Internship requires Design Project
        ];

        foreach ($prerequisites as $unitCode => $requiredUnitCodes) {
            $unit = Unit::where('code', $unitCode)->first();
            if (! $unit) {
                continue;
            }

            // Create prerequisite group for this unit
            $group = UnitPrerequisiteGroup::create([
                'unit_id' => $unit->id,
                'logic_operator' => 'AND',
                'description' => "Prerequisites for {$unit->code} - {$unit->name}",
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
}
