<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\UnitPrerequisiteGroup;
use App\Models\UnitPrerequisiteCondition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        UnitPrerequisiteCondition::query()->delete();
        UnitPrerequisiteGroup::query()->delete();
        Unit::query()->delete();

        // Create Units based on Bachelor of Computer Science curriculum
        $units = [
            // Computer Science Core Units
            ['code' => 'COS10009', 'name' => 'Introduction to Programming', 'credit_points' => 12.5],
            ['code' => 'COS10004', 'name' => 'Computer Systems', 'credit_points' => 12.5],
            ['code' => 'COS10026', 'name' => 'Computing Technology Inquiry Project', 'credit_points' => 12.5],
            ['code' => 'TNE10006', 'name' => 'Networks and Switching', 'credit_points' => 12.5],
            ['code' => 'COS10025', 'name' => 'Technology in an Indigenous Context Project', 'credit_points' => 12.5],
            ['code' => 'COS20007', 'name' => 'Object Oriented Programming', 'credit_points' => 12.5],
            ['code' => 'COS20019', 'name' => 'Cloud Computing Technology', 'credit_points' => 12.5],
            ['code' => 'COS20031', 'name' => 'Computing Technology Design Project', 'credit_points' => 12.5],
            ['code' => 'COS30019', 'name' => 'Introduction to Artificial Intelligence', 'credit_points' => 12.5],
            ['code' => 'COS30049', 'name' => 'Computing Technology Innovation Project', 'credit_points' => 12.5],
            ['code' => 'SWE30003', 'name' => 'Software Architecture and Design', 'credit_points' => 12.5],
            ['code' => 'COS30018', 'name' => 'Intelligent Systems', 'credit_points' => 12.5],
            ['code' => 'SWE40005', 'name' => 'Computing Technology Project A', 'credit_points' => 12.5],
            ['code' => 'COS40007', 'name' => 'Artificial Intelligence for Engineering', 'credit_points' => 12.5],
            ['code' => 'SWE30032', 'name' => 'Software Engineering Project', 'credit_points' => 12.5],
            ['code' => 'COS40006', 'name' => 'Computing Technology Project B', 'credit_points' => 12.5],
            // Additional units to satisfy curriculum requirements (total >= 27)
            ['code' => 'COS20024', 'name' => 'Data Structures and Algorithms', 'credit_points' => 12.5],
            ['code' => 'COS20096', 'name' => 'Database Systems', 'credit_points' => 12.5],
            ['code' => 'COS30017', 'name' => 'Software Testing and Reliability', 'credit_points' => 12.5],
            ['code' => 'COS30043', 'name' => 'Interface Design and Development', 'credit_points' => 12.5],
            ['code' => 'COS30045', 'name' => 'Advanced Network Design', 'credit_points' => 12.5],
            ['code' => 'COS40009', 'name' => 'Advanced Algorithms', 'credit_points' => 12.5],
            ['code' => 'COS40011', 'name' => 'Parallel and Distributed Computing', 'credit_points' => 12.5],
            ['code' => 'TNE20003', 'name' => 'Internet Technologies', 'credit_points' => 12.5],
            ['code' => 'COS10021', 'name' => 'Web Programming', 'credit_points' => 12.5],
            ['code' => 'COS20015', 'name' => 'IT Project Management', 'credit_points' => 12.5],
            ['code' => 'COS30082', 'name' => 'Data Visualization', 'credit_points' => 12.5],
            ['code' => 'COS40019', 'name' => 'Machine Learning Applications', 'credit_points' => 12.5],
            
            // Special units with credit requirements (as per seeder plan)
            ['code' => 'CAPSTONE_PROJECT', 'name' => 'Capstone Project', 'credit_points' => 12.5],
            ['code' => 'INTERNSHIP', 'name' => 'Industry Internship', 'credit_points' => 12.5],
            ['code' => 'ADVANCED_RESEARCH', 'name' => 'Advanced Research Project', 'credit_points' => 12.5],
        ];

        foreach ($units as $unitData) {
            Unit::create($unitData);
        }

        // Create prerequisite relationships after all units are created
        $this->createPrerequisiteRelationships();

        $this->command->info('Created ' . count($units) . ' units with prerequisite relationships.');
    }

    private function createPrerequisiteRelationships(): void
    {
        // Define prerequisite chains (PROG101 -> PROG201 -> PROG301 equivalent)
        $prerequisiteChains = [
            // Programming chain
            'COS20007' => ['COS10009'], // OOP requires Intro to Programming
            'COS20024' => ['COS10009'], // Data Structures requires Intro to Programming
            'COS30019' => ['COS20007'], // AI requires OOP
            'COS30018' => ['COS30019'], // Intelligent Systems requires Intro to AI
            'COS40007' => ['COS30018'], // AI for Engineering requires Intelligent Systems
            
            // Software Engineering chain
            'SWE30003' => ['COS20007'], // Software Architecture requires OOP
            'SWE30032' => ['SWE30003'], // SE Project requires Software Architecture
            'SWE40005' => ['SWE30032'], // Project A requires SE Project
            'COS40006' => ['SWE40005'], // Project B requires Project A
        ];

        // Create equivalent units (as per seeder plan)
        $equivalentUnits = [
            'COS20019' => ['TNE20003'], // Cloud Computing <-> Internet Technologies
            'COS30043' => ['COS10021'], // Interface Design <-> Web Programming
        ];

        // Create standard prerequisite relationships
        foreach ($prerequisiteChains as $unitCode => $prerequisites) {
            $unit = Unit::where('code', $unitCode)->first();
            if (!$unit) continue;

            // Create prerequisite group
            $group = UnitPrerequisiteGroup::create([
                'unit_id' => $unit->id,
                'logic_operator' => 'AND',
                'description' => "Prerequisites for {$unit->code}",
            ]);

            // Add prerequisite conditions
            foreach ($prerequisites as $prerequisiteCode) {
                $prerequisiteUnit = Unit::where('code', $prerequisiteCode)->first();
                if ($prerequisiteUnit) {
                    UnitPrerequisiteCondition::create([
                        'group_id' => $group->id,
                        'type' => 'prerequisite',
                        'required_unit_id' => $prerequisiteUnit->id,
                    ]);
                }
            }
        }

        // Create equivalent unit relationships
        foreach ($equivalentUnits as $unitCode => $equivalents) {
            $unit = Unit::where('code', $unitCode)->first();
            if (!$unit) continue;

            // Create equivalent group (OR logic)
            $group = UnitPrerequisiteGroup::create([
                'unit_id' => $unit->id,
                'logic_operator' => 'OR',
                'description' => "Equivalent units for {$unit->code}",
            ]);

            foreach ($equivalents as $equivalentCode) {
                $equivalentUnit = Unit::where('code', $equivalentCode)->first();
                if ($equivalentUnit) {
                    UnitPrerequisiteCondition::create([
                        'group_id' => $group->id,
                        'type' => 'anti_requisite',
                        'required_unit_id' => $equivalentUnit->id,
                    ]);
                }
            }
        }

        // Create credit requirement conditions for special units
        $creditRequirements = [
            'CAPSTONE_PROJECT' => 100, // 8 subjects * 12.5 = 100 credits
            'INTERNSHIP' => 87.5,      // 7 subjects * 12.5 = 87.5 credits 
            'ADVANCED_RESEARCH' => 125, // 10 subjects * 12.5 = 125 credits
        ];

        foreach ($creditRequirements as $unitCode => $requiredCredits) {
            $unit = Unit::where('code', $unitCode)->first();
            if (!$unit) continue;

            // Create credit requirement group
            $group = UnitPrerequisiteGroup::create([
                'unit_id' => $unit->id,
                'logic_operator' => 'AND',
                'description' => "Credit requirements for {$unit->code}",
            ]);

            // Add credit requirement condition
            UnitPrerequisiteCondition::create([
                'group_id' => $group->id,
                'type' => 'credit_requirement',
                'required_credits' => $requiredCredits,
                'free_text' => "Minimum {$requiredCredits} credit points required",
            ]);
        }

        $this->command->info('Created prerequisite relationships and credit requirements.');
    }
}
