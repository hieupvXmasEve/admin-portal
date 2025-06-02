<?php

namespace Database\Seeders;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\Semester;
use App\Models\Syllabus;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyllabusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing units and semesters
        $units = Unit::limit(5)->get();
        $semesters = Semester::limit(3)->get();

        if ($units->isEmpty() || $semesters->isEmpty()) {
            $this->command->warn('No units or semesters found. Please seed units and semesters first.');
            return;
        }

        foreach ($units as $unit) {
            // Create 1-2 syllabus per unit
            $syllabusCount = rand(1, 2);

            for ($i = 0; $i < $syllabusCount; $i++) {
                $syllabus = Syllabus::create([
                    'unit_id' => $unit->id,
                    'version' => 'v' . ($i + 1) . '.0',
                    'description' => "Course syllabus for {$unit->code} covering fundamental concepts and practical applications.",
                    'total_hours' => rand(60, 150),
                    'hours_per_session' => rand(2, 4),
                    'effective_from_semester_id' => $semesters->random()->id,
                    'is_active' => $i === 0, // First syllabus is active
                ]);

                // Create assessment components
                $this->createAssessmentComponents($syllabus);
            }
        }

        $this->command->info('Created syllabus with assessment components for ' . $units->count() . ' units.');
    }

    private function createAssessmentComponents(Syllabus $syllabus): void
    {
        // Define typical assessment structures
        $assessmentStructures = [
            [
                ['name' => 'Final Exam', 'type' => 'exam', 'weight' => 50],
                ['name' => 'Midterm Quiz', 'type' => 'quiz', 'weight' => 20],
                ['name' => 'Group Project', 'type' => 'project', 'weight' => 20],
                ['name' => 'Online Activities', 'type' => 'online_activity', 'weight' => 10],
            ],
            [
                ['name' => 'Final Exam', 'type' => 'exam', 'weight' => 40],
                ['name' => 'Assignment 1', 'type' => 'assignment', 'weight' => 15],
                ['name' => 'Assignment 2', 'type' => 'assignment', 'weight' => 15],
                ['name' => 'Project Work', 'type' => 'project', 'weight' => 25],
                ['name' => 'Class Participation', 'type' => 'other', 'weight' => 5],
            ],
            [
                ['name' => 'Portfolio', 'type' => 'project', 'weight' => 40],
                ['name' => 'Practical Exam', 'type' => 'exam', 'weight' => 30],
                ['name' => 'Weekly Quizzes', 'type' => 'quiz', 'weight' => 20],
                ['name' => 'Reflection Essays', 'type' => 'assignment', 'weight' => 10],
            ],
        ];

        $structure = $assessmentStructures[array_rand($assessmentStructures)];

        foreach ($structure as $componentData) {
            $component = AssessmentComponent::create([
                'syllabus_id' => $syllabus->id,
                'name' => $componentData['name'],
                'weight' => $componentData['weight'],
                'type' => $componentData['type'],
                'is_required_to_sit_final_exam' => $componentData['type'] !== 'other',
            ]);

            // Add details for some components
            if (in_array($componentData['type'], ['project', 'assignment']) && rand(0, 1)) {
                $this->createComponentDetails($component);
            }
        }
    }

    private function createComponentDetails(AssessmentComponent $component): void
    {
        $detailOptions = [
            'project' => [
                ['name' => 'Proposal', 'weight' => 20],
                ['name' => 'Implementation', 'weight' => 50],
                ['name' => 'Presentation', 'weight' => 30],
            ],
            'assignment' => [
                ['name' => 'Literature Review', 'weight' => 40],
                ['name' => 'Analysis', 'weight' => 35],
                ['name' => 'Conclusion', 'weight' => 25],
            ],
        ];

        $details = $detailOptions[$component->type] ?? [];

        foreach ($details as $detail) {
            AssessmentComponentDetail::create([
                'component_id' => $component->id,
                'name' => $detail['name'],
                'weight' => $detail['weight'],
            ]);
        }
    }
}
