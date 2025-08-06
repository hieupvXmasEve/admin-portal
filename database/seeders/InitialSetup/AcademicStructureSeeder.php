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
     * Creates one program (Bachelor of Information Technology) with one specialization (Artificial Intelligence)
     * and units based on the detailed curriculum specification from cs.txt
     */
    public function run(): void
    {
        $this->command->info('🎓 Creating academic structure for AI specialization...');

        // Clean existing data
        $this->cleanExistingData();

        // Create program
        $this->createProgram();

        // Create specialization
        //        $this->createSpecialization();

        // Create units
        //        $this->createUnits();

        // Create prerequisite relationships
        //        $this->createPrerequisiteRelationships();

        // Create equivalent units
        //        $this->createEquivalentUnits();

        $this->command->info('✅ Academic structure created successfully!');
    }

    private function cleanExistingData(): void
    {
        // Check if there are students or other dependencies
        $studentsCount = \App\Models\Student::count();
        $enrollmentsCount = \App\Models\Enrollment::whereNotNull('curriculum_version_id')->count();

        if ($studentsCount > 0 || $enrollmentsCount > 0) {
            $this->command->warn("⚠️  Found {$studentsCount} students and {$enrollmentsCount} enrollments. Skipping data cleanup to preserve foreign key relationships.");
            $this->command->info("📝 Will update existing records instead of recreating them.");
            return;
        }

        // Only clean if safe to do so
        try {
            EquivalentUnit::query()->delete();
            UnitPrerequisiteCondition::query()->delete();
            UnitPrerequisiteGroup::query()->delete();
            Unit::query()->delete();
            Specialization::query()->delete();
            Program::query()->delete();
            $this->command->info("🧹 Cleaned existing data successfully.");
        } catch (\Exception $e) {
            $this->command->warn("⚠️  Could not clean existing data due to foreign key constraints. Will update existing records instead.");
            $this->command->info("Error: " . $e->getMessage());
        }
    }

    private function createProgram(): void
    {
        // Bachelor of Semiconductor Engineering
        $semiconductorProgram = Program::updateOrCreate(
            ['code' => 'SE'],
            [
                'id' => 1,
                'name' => 'Semiconductor Engineering',
                'description' => 'A specialized program designed to equip students with knowledge and skills in semiconductor technology, fabrication, and microelectronics for careers in the semiconductor industry.',
            ]
        );

        // Artificial Intelligence
        $aiProgram = Program::updateOrCreate(
            ['code' => 'AI'],
            [
                'id' => 2,
                'name' => 'Artificial Intelligence',
                'description' => 'A focused program on artificial intelligence, machine learning, and intelligent systems, preparing students for advanced roles in AI research and industry.',
            ]
        );

        // Finance
        $financeProgram = Program::updateOrCreate(
            ['code' => 'FN'],
            [
                'id' => 3,
                'name' => 'Finance',
                'description' => 'A program providing comprehensive knowledge in finance, investment, and financial management, preparing students for careers in banking, investment, and corporate finance.',
            ]
        );

        // Business Administration
        $businessProgram = Program::updateOrCreate(
            ['code' => 'BA'],
            [
                'id' => 4,
                'name' => 'Business Administration',
                'description' => 'A broad-based program covering management, marketing, accounting, and entrepreneurship, preparing students for leadership roles in business and organizations.',
            ]
        );

        $this->command->info('📚 Created/Updated Bachelor of Information Technology program');
    }

    private function createSpecialization(): void
    {
        $specialization = Specialization::updateOrCreate(
            ['code' => 'AI', 'program_id' => 1], // Find by code and program
            [
                'name' => 'Artificial Intelligence',
                'description' => 'Specialization focusing on artificial intelligence, machine learning, and intelligent systems. This 3-year program consists of 300 credit points (24 units), with 8 core units, 8 major units, and 8 elective units.',
                'is_active' => true,
            ]
        );

        $this->command->info('🎯 Created/Updated Artificial Intelligence specialization');
    }

    private function createUnits(): void
    {
        $units = [
            // Core Units (8 units) - Required foundation courses
            ['code' => 'COS10004', 'name' => 'Computer Systems', 'credit_points' => 12.5],
            ['code' => 'COS10009', 'name' => 'Introduction to Programming', 'credit_points' => 12.5],
            ['code' => 'COS10025', 'name' => 'Technology in an Indigenous Context Project', 'credit_points' => 12.5],
            ['code' => 'COS10026', 'name' => 'Computing Technology Inquiry Project', 'credit_points' => 12.5],
            ['code' => 'COS20007', 'name' => 'Object Oriented Programming', 'credit_points' => 12.5],
            ['code' => 'COS40005', 'name' => 'Computing Technology Project A', 'credit_points' => 12.5],
            ['code' => 'COS40006', 'name' => 'Computing Technology Project B', 'credit_points' => 12.5],
            ['code' => 'TNE10006', 'name' => 'Networks and Switching', 'credit_points' => 12.5],

            // Major Units (8 units) - AI specialization courses
            ['code' => 'COS20019', 'name' => 'Cloud Computing Architecture', 'credit_points' => 12.5],
            ['code' => 'COS20031', 'name' => 'Computing Technology Design Project', 'credit_points' => 12.5],
            ['code' => 'COS30049', 'name' => 'Computing Technology Innovation Project', 'credit_points' => 12.5],
            ['code' => 'SWE30003', 'name' => 'Software Architectures and Design', 'credit_points' => 12.5],
            ['code' => 'COS30018', 'name' => 'Intelligent Systems', 'credit_points' => 12.5],
            ['code' => 'COS30019', 'name' => 'Introduction to Artificial Intelligence', 'credit_points' => 12.5],
            ['code' => 'COS30082', 'name' => 'Applied Machine Learning', 'credit_points' => 12.5],
            ['code' => 'COS40007', 'name' => 'Artificial Intelligence for Engineering', 'credit_points' => 12.5],

            // Elective Units (selected from the comprehensive list in cs.txt)
            // Technology & Programming Electives
            ['code' => 'ICT20015', 'name' => 'ICT Professional Internship', 'credit_points' => 12.5],
            ['code' => 'COS10005', 'name' => 'Web Programming', 'credit_points' => 12.5],
            ['code' => 'COS20015', 'name' => 'Fundamentals of Data Management', 'credit_points' => 12.5],
            ['code' => 'STA10003', 'name' => 'Foundation of Statistics', 'credit_points' => 12.5],
            ['code' => 'COS10022', 'name' => 'Data Science Principles', 'credit_points' => 12.5],
            ['code' => 'COS20028', 'name' => 'Big Data Architecture and Application', 'credit_points' => 12.5],
            ['code' => 'COS30045', 'name' => 'Data Visualisation', 'credit_points' => 12.5],
            ['code' => 'SWE40006', 'name' => 'Software Deployment and Evolution', 'credit_points' => 12.5],
            ['code' => 'COS30017', 'name' => 'Software Development for Mobile Devices', 'credit_points' => 12.5],
            ['code' => 'COS30020', 'name' => 'Advanced Web Development', 'credit_points' => 12.5],
            ['code' => 'SWE30011', 'name' => 'IoT Programming', 'credit_points' => 12.5],
            ['code' => 'TNE10005', 'name' => 'Network Administration', 'credit_points' => 12.5],
            ['code' => 'COS30008', 'name' => 'Data Structures and Patterns', 'credit_points' => 12.5],
            ['code' => 'COS30043', 'name' => 'Interface Design and Development', 'credit_points' => 12.5],
            ['code' => 'COS40003', 'name' => 'Concurrent Programming', 'credit_points' => 12.5],
            ['code' => 'SWE30009', 'name' => 'Software Testing and Reliability', 'credit_points' => 12.5],

            // Security Electives
            ['code' => 'COS20030', 'name' => 'Malware Analysis', 'credit_points' => 12.5],
            ['code' => 'COS30015', 'name' => 'IT Security', 'credit_points' => 12.5],
            ['code' => 'TNE20003', 'name' => 'Internet and Cybersecurity for Engineering Applications', 'credit_points' => 12.5],
            ['code' => 'TNE30009', 'name' => 'Network Security and Resilience', 'credit_points' => 12.5],

            // Business Electives
            ['code' => 'ECO10005', 'name' => 'Economics for Business Decision Making', 'credit_points' => 12.5],
            ['code' => 'ACC10007', 'name' => 'Financial Information for Decision Making', 'credit_points' => 12.5],
            ['code' => 'MGT10009', 'name' => 'Contemporary Management Principles', 'credit_points' => 12.5],
            ['code' => 'MKT10009', 'name' => 'Marketing and the Consumer Experience', 'credit_points' => 12.5],
            ['code' => 'BUS10015', 'name' => 'Creative Mindset and Entrepreneurship', 'credit_points' => 12.5],
            ['code' => 'INF10024', 'name' => 'Business Digitalisation', 'credit_points' => 12.5],
            ['code' => 'BUS10014', 'name' => 'Business for Sustainability, Social Change and Impact', 'credit_points' => 12.5],
            ['code' => 'HRM20017', 'name' => 'Managing Workplace Relations', 'credit_points' => 12.5],
            ['code' => 'MGT20007', 'name' => 'Organisational Behaviour', 'credit_points' => 12.5],
            ['code' => 'INF20016', 'name' => 'Big Data Management', 'credit_points' => 12.5],
            ['code' => 'LAW20019', 'name' => 'Law of Commerce', 'credit_points' => 12.5],

            // International Business Electives
            ['code' => 'INB10002', 'name' => 'International Business Operations', 'credit_points' => 12.5],
            ['code' => 'INB20009', 'name' => 'Global and Digital Marketplaces', 'credit_points' => 12.5],
            ['code' => 'INB20012', 'name' => 'Asian Regionalism and Global Business', 'credit_points' => 12.5],
            ['code' => 'SCM20003', 'name' => 'Global Logistics and Supply Chain Management', 'credit_points' => 12.5],

            // Marketing Electives
            ['code' => 'MKT20019', 'name' => 'Marketing Research and Analytics', 'credit_points' => 12.5],
            ['code' => 'MKT20021', 'name' => 'Integrated Marketing Communication', 'credit_points' => 12.5],
            ['code' => 'MKT20025', 'name' => 'Consumer Behaviour', 'credit_points' => 12.5],
            ['code' => 'MKT20031', 'name' => 'Marketing and Innovation', 'credit_points' => 12.5],
            ['code' => 'MKT20032', 'name' => 'Frontiers in Digital Marketing', 'credit_points' => 12.5],

            // Media & Communication Electives
            ['code' => 'MDA10012', 'name' => 'Communicating with Data', 'credit_points' => 12.5],
            ['code' => 'MDA10018', 'name' => 'Content Creator Lab', 'credit_points' => 12.5],
            ['code' => 'MDA10001', 'name' => 'Introduction to Media Studies', 'credit_points' => 12.5],
            ['code' => 'MDA10008', 'name' => 'Global Media Industries', 'credit_points' => 12.5],
            ['code' => 'DCO10001', 'name' => 'Concepts and Narratives', 'credit_points' => 12.5],
            ['code' => 'DCO10002', 'name' => 'Digital Design', 'credit_points' => 12.5],
            ['code' => 'DCO10007', 'name' => 'Visual Communication Studio', 'credit_points' => 12.5],
            ['code' => 'DCO20004', 'name' => 'Web Design', 'credit_points' => 12.5],
            ['code' => 'MDA10015', 'name' => 'Social Media Strategy', 'credit_points' => 12.5],
            ['code' => 'MDA20028', 'name' => 'Business of Media and Entrepreneurship', 'credit_points' => 12.5],
            ['code' => 'MDA10013', 'name' => 'Digital Self/Digital Community', 'credit_points' => 12.5],
            ['code' => 'JOU20007', 'name' => 'Interactive storytelling', 'credit_points' => 12.5],
            ['code' => 'MDA20026', 'name' => 'Data Narratives', 'credit_points' => 12.5],

            // Advertising Electives
            ['code' => 'ADV10001', 'name' => 'Principles of Advertising', 'credit_points' => 12.5],
            ['code' => 'ADV20004', 'name' => 'Advertising Issues and Impact', 'credit_points' => 12.5],
            ['code' => 'ADV20005', 'name' => 'Creativity and Ideation', 'credit_points' => 12.5],
            ['code' => 'ADV10002', 'name' => 'Digital Advertising', 'credit_points' => 12.5],
            ['code' => 'ADV20003', 'name' => 'Search, Social Media and Video Marketing', 'credit_points' => 12.5],

            // Communication & Public Relations Electives
            ['code' => 'COM10007', 'name' => 'Professional Communication Practice', 'credit_points' => 12.5],
            ['code' => 'PUB10001', 'name' => 'Introduction to Public Relations Theory and Practice', 'credit_points' => 12.5],
            ['code' => 'PUB20001', 'name' => 'Global Public Relations Practice', 'credit_points' => 12.5],
            ['code' => 'PUB20003', 'name' => 'Public Relations Writing', 'credit_points' => 12.5],
            ['code' => 'PUB20004', 'name' => 'Issues, Crisis and Risk Communication', 'credit_points' => 12.5],

            // Second Major Units (Software Technology)
            ['code' => 'COS20001', 'name' => 'User-Centred Design', 'credit_points' => 12.5],

            // Marketing Co-major Units
            ['code' => 'MKT10007', 'name' => 'Fundamentals of Marketing', 'credit_points' => 12.5],
            ['code' => 'MKT30016', 'name' => 'Marketing Strategy and Planning', 'credit_points' => 12.5],
            ['code' => 'MKT30018', 'name' => 'Marketing Insights', 'credit_points' => 12.5],

            // Advertising Co-major Units
            ['code' => 'ADV20001', 'name' => 'Advertising Issues: Regulations, Ethics & Cultural Considerations', 'credit_points' => 12.5],
            ['code' => 'ADV20002', 'name' => 'Concept Development and Copywriting', 'credit_points' => 12.5],
            ['code' => 'ADV30001', 'name' => 'Advertising Media Planning and Purchasing', 'credit_points' => 12.5],
            ['code' => 'ADV30002', 'name' => 'Advertising Management and Campaigns Project', 'credit_points' => 12.5],
            ['code' => 'COM30002', 'name' => 'Professional Practice: Client and Agency Management', 'credit_points' => 12.5],
            ['code' => 'MDA20011', 'name' => 'Sports/Advertising/Media', 'credit_points' => 12.5],
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

    private function createPrerequisiteRelationships(): void
    {
        $prerequisites = [
            // Programming progression
            'COS20007' => ['COS10009'], // OOP requires Intro to Programming
            'COS30019' => ['COS20007'], // AI requires OOP
            'COS30018' => ['COS30019'], // Intelligent Systems requires Intro to AI
            'COS40007' => ['COS30018'], // AI for Engineering requires Intelligent Systems
            'COS30082' => ['COS30019'], // Applied Machine Learning requires Intro to AI

            // Project progression
            'COS40005' => ['COS20031'], // Project A requires Design Project
            'COS40006' => ['COS40005'], // Project B requires Project A
            'COS30049' => ['COS20031'], // Innovation Project requires Design Project
            'COS20031' => ['COS10026'], // Design Project requires Inquiry Project

            // Advanced courses
            'COS30020' => ['COS20015'], // Advanced Web Development requires Data Management
            'COS30043' => ['COS20007'], // Interface Design requires OOP
            'SWE30003' => ['COS20007'], // Software Architecture requires OOP
            'SWE30009' => ['SWE30003'], // Software Testing requires Software Architecture
            'SWE40006' => ['SWE30003'], // Software Deployment requires Software Architecture

            // Data and Analytics
            'COS20028' => ['COS10022'], // Big Data requires Data Science Principles
            'COS30045' => ['STA10003'], // Data Visualisation requires Statistics
            'INF20016' => ['COS20015'], // Big Data Management requires Data Management
            'MDA20026' => ['MDA10012'], // Data Narratives requires Communicating with Data

            // Security progression
            'COS30015' => ['COS10004'], // IT Security requires Computer Systems
            'TNE20003' => ['TNE10006'], // Internet Security requires Networks and Switching
            'TNE30009' => ['TNE20003'], // Network Security requires Internet Security
            'COS20030' => ['COS30015'], // Malware Analysis requires IT Security

            // Mobile and IoT
            'SWE30011' => ['COS30017'], // IoT Programming requires Mobile Development
            'COS30017' => ['COS20007'], // Mobile Development requires OOP

            // Network progression
            'TNE10005' => ['TNE10006'], // Network Administration requires Networks and Switching

            // Business progression
            'MGT20007' => ['MGT10009'], // Organisational Behaviour requires Management Principles
            'MKT20019' => ['MKT10009'], // Marketing Research requires Marketing Experience
            'MKT20021' => ['MKT10009'], // Integrated Marketing requires Marketing Experience
            'MKT20025' => ['MKT10009'], // Consumer Behaviour requires Marketing Experience
            'MKT20031' => ['MKT20019'], // Marketing Innovation requires Marketing Research
            'MKT20032' => ['MKT20021'], // Digital Marketing requires Integrated Marketing

            // Advanced projects and final units
            'ICT20015' => ['COS30019', 'COS30018'], // Internship requires AI knowledge
        ];

        foreach ($prerequisites as $unitCode => $requiredUnitCodes) {
            $unit = Unit::where('code', $unitCode)->first();
            if (!$unit) continue;

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

    private function createEquivalentUnits(): void
    {
        // Create equivalent relationships for units that can substitute each other
        $equivalents = [
            // Data related equivalents
            'COS10022' => 'STA10003', // Data Science Principles equivalent to Statistics
            'COS20015' => 'INF20016', // Data Management equivalent to Big Data Management

            // Design and interface equivalents
            'COS30043' => 'DCO10002', // Interface Design equivalent to Digital Design
            'DCO20004' => 'COS10005', // Web Design equivalent to Web Programming

            // Communication equivalents
            'COM10007' => 'MDA10012', // Professional Communication equivalent to Communicating with Data
            'PUB20003' => 'ADV20002', // PR Writing equivalent to Copywriting

            // Business equivalents
            'MKT10009' => 'MKT10007', // Marketing Experience equivalent to Fundamentals of Marketing
            'BUS10015' => 'MGT10009', // Creative Entrepreneurship equivalent to Management Principles
        ];

        foreach ($equivalents as $unitCode => $equivalentUnitCode) {
            $unit = Unit::where('code', $unitCode)->first();
            $equivalentUnit = Unit::where('code', $equivalentUnitCode)->first();

            if ($unit && $equivalentUnit) {
                // Create bidirectional equivalent relationships
                EquivalentUnit::create([
                    'unit_id' => $unit->id,
                    'equivalent_unit_id' => $equivalentUnit->id,
                    'reason' => 'Similar learning outcomes and credit value equivalence',
                ]);

                EquivalentUnit::create([
                    'unit_id' => $equivalentUnit->id,
                    'equivalent_unit_id' => $unit->id,
                    'reason' => 'Similar learning outcomes and credit value equivalence',
                ]);
            }
        }

        $this->command->info('🔄 Created equivalent unit mappings');
    }
}
