<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\CourseOffering;
use App\Models\Syllabus;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\Unit;
use App\Models\CurriculumUnit;
use Illuminate\Database\Seeder;

class SyllabusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates syllabi and assessment components for course offerings
     */
    public function run(): void
    {
        $this->command->info('📋 Creating syllabi and assessments...');

        // Clean existing data
        $this->cleanExistingData();

        // Create syllabi for course offerings
        $this->createSyllabi();

        $this->command->info('✅ Syllabi and assessments created successfully!');
    }

    private function cleanExistingData(): void
    {
        AssessmentComponentDetail::query()->delete();
        AssessmentComponent::query()->delete();
        Syllabus::query()->delete();
    }

    private function createSyllabi(): void
    {
        $curriculumUnits = CurriculumUnit::with(['unit', 'semester'])->get();

        if ($curriculumUnits->isEmpty()) {
            throw new \Exception('Curriculum units not found. Please ensure curriculum setup is complete.');
        }

        foreach ($curriculumUnits as $curriculumUnit) {
            $this->createSyllabusForCurriculumUnit($curriculumUnit);
        }
    }

    private function createSyllabusForCurriculumUnit(CurriculumUnit $curriculumUnit): void
    {
        $unit = $curriculumUnit->unit;
        $semester = $curriculumUnit->semester;

        // If no semester assigned to curriculum unit, use current active semester
        if (!$semester) {
            $semester = \App\Models\Semester::where('is_active', true)->first();
        }

        // Create syllabus
        $syllabus = Syllabus::create([
            'curriculum_unit_id' => $curriculumUnit->id,
            'version' => 'v1.0',
            'description' => $this->generateSyllabusDescription($unit),
            'total_hours' => 120,
            // 'hours_per_session' => $this->getHoursPerSession($unit),
            'hours_per_session' => 3,
            'is_active' => true,
        ]);

        // Create assessment components
        $this->createAssessmentComponents($syllabus, $unit);

        $semesterName = $semester ? $semester->name : 'General';
        $this->command->info("📋 Created syllabus for {$unit->code} - {$semesterName}");
    }

    private function generateSyllabusDescription(Unit $unit): string
    {
        $descriptions = [
            'COS' => "This unit provides comprehensive coverage of computer science fundamentals with hands-on programming experience. Students will develop problem-solving skills and learn industry-standard practices.",
            'TNE' => "This unit focuses on network engineering principles and practical implementation. Students will gain experience with network design, configuration, and troubleshooting.",
            'MKT' => "This unit explores marketing concepts and strategies in the modern business environment. Students will learn to analyze markets, develop campaigns, and understand consumer behavior.",
            'ACC' => "This unit covers fundamental accounting principles and practices. Students will learn financial reporting, analysis, and decision-making skills essential for business success.",
            'HRM' => "This unit examines human resource management practices and organizational behavior. Students will develop skills in recruitment, performance management, and employee development.",
            'ECO' => "This unit introduces economic principles and their application to business decisions. Students will analyze market structures, economic indicators, and policy implications.",
            'FIN' => "This unit covers corporate finance principles and investment analysis. Students will learn financial planning, risk assessment, and capital budgeting techniques.",
            'ENG' => "This unit develops engineering problem-solving skills and technical communication. Students will apply mathematical and scientific principles to real-world engineering challenges.",
            'CIV' => "This unit focuses on civil engineering design and construction principles. Students will learn structural analysis, materials science, and project management.",
            'MEC' => "This unit covers mechanical engineering fundamentals including thermodynamics and fluid mechanics. Students will design and analyze mechanical systems.",
            'ELE' => "This unit introduces electrical engineering concepts including circuit analysis and electronic systems. Students will design and test electrical circuits.",
            'MAT' => "This unit provides mathematical foundations essential for technical disciplines. Students will develop analytical and problem-solving skills through theoretical and applied mathematics.",
        ];

        $unitPrefix = substr($unit->code, 0, 3);
        $baseDescription = $descriptions[$unitPrefix] ?? "This unit provides comprehensive coverage of the subject matter with theoretical foundations and practical applications.";

        return $baseDescription . " Assessment includes a combination of assignments, projects, and examinations designed to evaluate understanding and application of key concepts.";
    }

    private function getTotalHours(Unit $unit): int
    {
        // Calculate based on credit points (typically 10 hours per credit point)
        return (int)($unit->credit_points * 10);
    }

    private function getHoursPerSession(Unit $unit): int
    {
        // Most units have 2-3 hour sessions
        if (str_contains($unit->code, 'LAB') || str_contains($unit->name, 'Lab')) {
            return 3; // Lab sessions are longer
        }

        return rand(2, 3);
    }

    private function createAssessmentComponents(Syllabus $syllabus, Unit $unit): void
    {
        $assessmentStructure = $this->getFoundationAssessmentStructure();

        foreach ($assessmentStructure as $componentData) {
            $component = AssessmentComponent::create([
                'syllabus_id' => $syllabus->id,
                'name' => $componentData['name'],
                'weight' => $componentData['weight'],
                'type' => $componentData['type'],
                'is_required_to_sit_final_exam' => $componentData['required_for_final'] ?? true,
            ]);

            // Create component details for each assessment component
            foreach ($componentData['details'] as $detailData) {
                AssessmentComponentDetail::create([
                    'assessment_component_id' => $component->id,
                    'name' => $detailData['name'],
                    'weight' => $detailData['weight'],
                ]);
            }
        }
    }

    private function getFoundationAssessmentStructure(): array
    {
        return [
            [
                'name' => 'Weekly Quizzes',
                'weight' => 20.00,
                'type' => 'quiz',
                'required_for_final' => false,
                'details' => [
                    ['name' => 'Quiz 1', 'weight' => 4.00],
                    ['name' => 'Quiz 2', 'weight' => 4.00],
                    ['name' => 'Quiz 3', 'weight' => 4.00],
                    ['name' => 'Quiz 4', 'weight' => 4.00],
                    ['name' => 'Quiz 5', 'weight' => 4.00],
                ]
            ],
            [
                'name' => 'Programming Assignments',
                'weight' => 40.00,
                'type' => 'assignment',
                'required_for_final' => true,
                'details' => [
                    ['name' => 'Assignment 1', 'weight' => 15.00],
                    ['name' => 'Assignment 2', 'weight' => 15.00],
                    ['name' => 'Assignment 3', 'weight' => 10.00],
                ]
            ],
            [
                'name' => 'Mid-term Exam',
                'weight' => 15.00,
                'type' => 'exam',
                'required_for_final' => true,
                'details' => [
                    ['name' => 'Mid-term Exam', 'weight' => 15.00],
                ]
            ],
            [
                'name' => 'Final Exam',
                'weight' => 25.00,
                'type' => 'exam',
                'required_for_final' => true,
                'details' => [
                    ['name' => 'Final Exam', 'weight' => 25.00],
                ]
            ],
        ];
    }
}
