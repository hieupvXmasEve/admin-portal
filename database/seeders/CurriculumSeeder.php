<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Program;
use App\Models\CurriculumVersion;
use App\Models\Unit;
use App\Models\CurriculumUnit;
use App\Models\UnitPrerequisite;
use App\Models\EquivalentUnit;
use App\Models\Semester;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default campus and semester first
        $campus = \App\Models\Campus::first();
        if (!$campus) {
            $campus = \App\Models\Campus::create([
                'name' => 'Default Campus',
                'code' => 'DC',
                'address' => 'Default Address',
            ]);
        }

        // Create a default semester
        $semester = \App\Models\Semester::create([
            'campus_id' => $campus->id,
            'code' => 'FALL2024',
            'name' => 'Fall 2024',
            'semester_type' => 'fall',
            'year' => '2024',
            'start_date' => '2024-08-26',
            'end_date' => '2024-12-15',
            'enrollment_start_date' => '2024-08-01 08:00:00',
            'enrollment_end_date' => '2024-08-25 23:59:59',
            'is_active' => true,
            'is_archived' => false,
            'is_current' => true,
            'is_registration_open' => true,
            'locked_status' => 'unlocked',
            'enrollment_start_date' => '2024-08-26',
            'enrollment_end_date' => '2024-12-15',
            'add_drop_deadline' => '2024-09-01',
            'withdrawal_deadline' => '2024-12-15',
            'final_exam_start' => '2024-12-16',
            'final_exam_end' => '2024-12-20',
            'max_credit_load' => 18.00,
            'min_credit_load' => 12.00,
            'is_attendance_locked' => false,
            'is_certificate_locked' => false,
            'has_tuition_fee' => true,
            'has_gc_fee' => true,
        ]);

        // Create Units
        $units = [
            ['code' => 'CS101', 'name' => 'Introduction to Computer Science', 'credit_points' => 4.00],
            ['code' => 'CS102', 'name' => 'Programming Fundamentals', 'credit_points' => 4.00],
            ['code' => 'CS201', 'name' => 'Data Structures and Algorithms', 'credit_points' => 4.00],
            ['code' => 'CS301', 'name' => 'Database Systems', 'credit_points' => 4.00],
            ['code' => 'CS401', 'name' => 'Software Engineering', 'credit_points' => 4.00],

            ['code' => 'BUS101', 'name' => 'Introduction to Business', 'credit_points' => 3.00],
            ['code' => 'BUS201', 'name' => 'Marketing Principles', 'credit_points' => 3.00],
            ['code' => 'BUS301', 'name' => 'Financial Management', 'credit_points' => 3.00],
            ['code' => 'BUS401', 'name' => 'Strategic Management', 'credit_points' => 3.00],

            ['code' => 'GC101', 'name' => 'Global Citizenship Foundations', 'credit_points' => 3.00],
            ['code' => 'GC201', 'name' => 'Cultural Studies', 'credit_points' => 3.00],
            ['code' => 'GC301', 'name' => 'International Relations', 'credit_points' => 3.00],

            ['code' => 'VV101', 'name' => 'Vovinam Basics', 'credit_points' => 2.00],
            ['code' => 'VV201', 'name' => 'Advanced Vovinam Techniques', 'credit_points' => 2.00],
            ['code' => 'VV301', 'name' => 'Vovinam Philosophy', 'credit_points' => 2.00],

            ['code' => 'MC101', 'name' => 'Media Production', 'credit_points' => 3.00],
            ['code' => 'MC201', 'name' => 'Digital Storytelling', 'credit_points' => 3.00],
            ['code' => 'MC301', 'name' => 'Advanced Media Technology', 'credit_points' => 3.00],

            // Alternative equivalent unit
            ['code' => 'CS102A', 'name' => 'Programming Fundamentals (Alternative)', 'credit_points' => 4.00],
        ];

        foreach ($units as $unitData) {
            // Check if the unit already exists
            $existing = Unit::where('code', $unitData['code'])->first();
            if (!$existing) {
                Unit::create($unitData);
            }
        }

        // Create Curriculum Versions for each program
        $programs = Program::all();

        foreach ($programs as $program) {
            $curriculumVersion = CurriculumVersion::create([
                'program_id' => $program->id,
                'version_code' => $program->name . '_V1.0',
                'semester_id' => $semester->id,
            ]);

            // Add units to curriculum based on program
            $this->seedCurriculumUnits($curriculumVersion, $program->name);
        }

        // Create Prerequisites
        $this->seedPrerequisites();

        // Create Equivalent Units
        $this->seedEquivalentUnits($semester);
    }

    private function seedCurriculumUnits(CurriculumVersion $curriculumVersion, string $programName): void
    {
        $unitMappings = [
            'IT' => [
                ['code' => 'CS101', 'group_type' => 'core'],
                ['code' => 'CS102', 'group_type' => 'core'],
                ['code' => 'CS201', 'group_type' => 'core'],
                ['code' => 'CS301', 'group_type' => 'major'],
                ['code' => 'CS401', 'group_type' => 'major'],
                ['code' => 'BUS101', 'group_type' => 'elective'],
            ],
            'Business' => [
                ['code' => 'BUS101', 'group_type' => 'core'],
                ['code' => 'BUS201', 'group_type' => 'core'],
                ['code' => 'BUS301', 'group_type' => 'major'],
                ['code' => 'BUS401', 'group_type' => 'major'],
                ['code' => 'CS101', 'group_type' => 'elective'],
                ['code' => 'GC101', 'group_type' => 'minor'],
            ],
            'Global Citizen' => [
                ['code' => 'GC101', 'group_type' => 'core'],
                ['code' => 'GC201', 'group_type' => 'core'],
                ['code' => 'GC301', 'group_type' => 'major'],
                ['code' => 'BUS101', 'group_type' => 'elective'],
            ],
            'Vovinam' => [
                ['code' => 'VV101', 'group_type' => 'core'],
                ['code' => 'VV201', 'group_type' => 'core'],
                ['code' => 'VV301', 'group_type' => 'major'],
                ['code' => 'GC101', 'group_type' => 'elective'],
            ],
            'MC' => [
                ['code' => 'MC101', 'group_type' => 'core'],
                ['code' => 'MC201', 'group_type' => 'core'],
                ['code' => 'MC301', 'group_type' => 'major'],
                ['code' => 'CS101', 'group_type' => 'second_major'],
            ],
        ];

        if (isset($unitMappings[$programName])) {
            foreach ($unitMappings[$programName] as $mapping) {
                $unit = Unit::where('code', $mapping['code'])->first();
                if ($unit) {
                    CurriculumUnit::create([
                        'curriculum_version_id' => $curriculumVersion->id,
                        'unit_id' => $unit->id,
                        'group_type' => $mapping['group_type'],
                    ]);
                }
            }
        }
    }

    private function seedPrerequisites(): void
    {
        $prerequisiteGroups = [
            // Computer Science Units
            'CS' => [
                'unit_code' => null,
                'logic_operator' => 'AND',
                'description' => 'Computer Science prerequisites',
                'conditions' => [
                    // CS102 requires CS101
                    ['unit_code' => 'CS102', 'required_unit_code' => 'CS101', 'type' => 'prerequisite'],
                    // CS201 requires CS102
                    ['unit_code' => 'CS201', 'required_unit_code' => 'CS102', 'type' => 'prerequisite'],
                    // CS301 requires CS201
                    ['unit_code' => 'CS301', 'required_unit_code' => 'CS201', 'type' => 'prerequisite'],
                    // CS401 requires CS301
                    ['unit_code' => 'CS401', 'required_unit_code' => 'CS301', 'type' => 'prerequisite'],
                ]
            ],
            // Business Units
            'BUS' => [
                'unit_code' => null,
                'logic_operator' => 'AND',
                'description' => 'Business prerequisites',
                'conditions' => [
                    // BUS201 has assumed knowledge of BUS101
                    ['unit_code' => 'BUS201', 'required_unit_code' => 'BUS101', 'type' => 'assumed_knowledge'],
                ]
            ],
            // Vovinam Units
            'VV' => [
                'unit_code' => null,
                'logic_operator' => 'AND',
                'description' => 'Vovinam prerequisites',
                'conditions' => [
                    // VV201 requires VV101
                    ['unit_code' => 'VV201', 'required_unit_code' => 'VV101', 'type' => 'prerequisite'],
                    // VV301 requires VV201
                    ['unit_code' => 'VV301', 'required_unit_code' => 'VV201', 'type' => 'prerequisite'],
                ]
            ],
            // Media Communication Units
            'MC' => [
                'unit_code' => null,
                'logic_operator' => 'AND',
                'description' => 'Media Communication prerequisites',
                'conditions' => [
                    // MC201 requires MC101
                    ['unit_code' => 'MC201', 'required_unit_code' => 'MC101', 'type' => 'prerequisite'],
                ]
            ],
        ];

        foreach ($prerequisiteGroups as $key => $groupData) {
            foreach ($groupData['conditions'] as $condition) {
                $unit = Unit::where('code', $condition['unit_code'])->first();
                $requiredUnit = Unit::where('code', $condition['required_unit_code'])->first();

                if ($unit && $requiredUnit) {
                    // Create or get a group for this unit
                    $group = \App\Models\UnitPrerequisiteGroup::firstOrCreate(
                        ['unit_id' => $unit->id],
                        [
                            'logic_operator' => $groupData['logic_operator'],
                            'description' => $groupData['description'] . " for {$unit->code}",
                        ]
                    );

                    // Create the condition
                    \App\Models\UnitPrerequisiteCondition::create([
                        'group_id' => $group->id,
                        'type' => $condition['type'],
                        'required_unit_id' => $requiredUnit->id,
                    ]);
                }
            }
        }
    }

    private function seedEquivalentUnits($semester): void
    {
        // CS102 and CS102A are equivalent
        $cs102 = Unit::where('code', 'CS102')->first();
        $cs102a = Unit::where('code', 'CS102A')->first();

        if ($cs102 && $cs102a) {
            // Check if equivalent unit already exists
            $existingEquivalent = EquivalentUnit::where('unit_id', $cs102->id)
                ->where('equivalent_unit_id', $cs102a->id)
                ->first();

            if (!$existingEquivalent) {
                EquivalentUnit::create([
                    'unit_id' => $cs102->id,
                    'equivalent_unit_id' => $cs102a->id,
                    'reason' => 'Alternative programming fundamentals course with same learning outcomes',
                    'valid_from_semester_id' => $semester?->id,
                ]);
            }

            // Check reverse relationship
            $existingReverseEquivalent = EquivalentUnit::where('unit_id', $cs102a->id)
                ->where('equivalent_unit_id', $cs102->id)
                ->first();

            if (!$existingReverseEquivalent) {
                // Create the reverse relationship
                EquivalentUnit::create([
                    'unit_id' => $cs102a->id,
                    'equivalent_unit_id' => $cs102->id,
                    'reason' => 'Alternative programming fundamentals course with same learning outcomes',
                    'valid_from_semester_id' => $semester?->id,
                ]);
            }
        }
    }
}
