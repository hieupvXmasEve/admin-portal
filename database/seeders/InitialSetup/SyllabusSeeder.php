<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\CourseOffering;
use App\Models\Syllabus;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\Unit;
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
        $courseOfferings = CourseOffering::with(['unit', 'semester'])->get();

        if ($courseOfferings->isEmpty()) {
            throw new \Exception('Course offerings not found. Please run SemesterAndOfferingSeeder first.');
        }

        // Group offerings by unit and semester to avoid duplicate syllabi
        $groupedOfferings = $courseOfferings->groupBy(function ($offering) {
            return $offering->unit_id . '_' . $offering->semester_id;
        });

        foreach ($groupedOfferings as $group) {
            // Take the first offering from each group to create syllabus
            $courseOffering = $group->first();
            $this->createSyllabusForOffering($courseOffering);
        }
    }

    private function createSyllabusForOffering(CourseOffering $courseOffering): void
    {
        $unit = $courseOffering->unit;
        $semester = $courseOffering->semester;

        // Create syllabus
        $syllabus = Syllabus::create([
            'unit_id' => $unit->id,
            'version' => 'v1.0',
            'description' => $this->generateSyllabusDescription($unit),
            'total_hours' => $this->getTotalHours($unit),
            'hours_per_session' => $this->getHoursPerSession($unit),
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);

        // Create assessment components
        $this->createAssessmentComponents($syllabus, $unit);

        $this->command->info("📋 Created syllabus for {$unit->code} - {$semester->name}");
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
        $unitCode = $unit->code;
        $assessmentStructure = $this->getAssessmentStructure($unitCode);

        foreach ($assessmentStructure as $componentData) {
            $component = AssessmentComponent::create([
                'syllabus_id' => $syllabus->id,
                'name' => $componentData['name'],
                'weight' => $componentData['weight'],
                'type' => $componentData['type'],
                'is_required_to_sit_final_exam' => $componentData['required_for_final'] ?? true,
            ]);

            // Create component details if specified
            if (isset($componentData['details'])) {
                foreach ($componentData['details'] as $detailData) {
                    AssessmentComponentDetail::create([
                        'component_id' => $component->id,
                        'name' => $detailData['name'],
                        'weight' => $detailData['weight'],
                    ]);
                }
            }
        }
    }

    private function getAssessmentStructure(string $unitCode): array
    {
        $unitPrefix = substr($unitCode, 0, 3);
        $unitLevel = substr($unitCode, 3, 1); // 1, 2, 3, 4

        // Define assessment structures based on unit type and level
        $structures = [
            'foundation' => [
                ['name' => 'Weekly Quizzes', 'weight' => 20.00, 'type' => 'quiz', 'required_for_final' => false],
                ['name' => 'Programming Assignments', 'weight' => 40.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Mid-term Exam', 'weight' => 15.00, 'type' => 'exam', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 25.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'programming' => [
                [
                    'name' => 'Coding Assignments',
                    'weight' => 50.00,
                    'type' => 'assignment',
                    'required_for_final' => true,
                    'details' => [
                        ['name' => 'Assignment 1', 'weight' => 15.00],
                        ['name' => 'Assignment 2', 'weight' => 15.00],
                        ['name' => 'Assignment 3', 'weight' => 20.00],
                    ]
                ],
                ['name' => 'Project', 'weight' => 30.00, 'type' => 'project', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 20.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'business' => [
                ['name' => 'Case Study Analysis', 'weight' => 25.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Group Project', 'weight' => 30.00, 'type' => 'project', 'required_for_final' => true],
                ['name' => 'Presentation', 'weight' => 15.00, 'type' => 'other', 'required_for_final' => false],
                ['name' => 'Final Exam', 'weight' => 30.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'engineering' => [
                ['name' => 'Laboratory Reports', 'weight' => 30.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Design Project', 'weight' => 35.00, 'type' => 'project', 'required_for_final' => true],
                ['name' => 'Mid-term Test', 'weight' => 15.00, 'type' => 'exam', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 20.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'mathematics' => [
                ['name' => 'Problem Sets', 'weight' => 25.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Mid-term Exam', 'weight' => 25.00, 'type' => 'exam', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 50.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'capstone' => [
                ['name' => 'Project Proposal', 'weight' => 10.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Progress Reports', 'weight' => 20.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Final Project', 'weight' => 50.00, 'type' => 'project', 'required_for_final' => true],
                ['name' => 'Presentation', 'weight' => 20.00, 'type' => 'other', 'required_for_final' => true],
            ],
        ];

        // Determine assessment type based on unit code
        if (in_array($unitPrefix, ['COS', 'TNE']) && $unitLevel === '1') {
            return $structures['foundation'];
        } elseif (in_array($unitPrefix, ['COS', 'TNE']) && in_array($unitLevel, ['2', '3'])) {
            return $structures['programming'];
        } elseif (in_array($unitPrefix, ['MKT', 'ACC', 'HRM', 'ECO', 'FIN'])) {
            return $structures['business'];
        } elseif (in_array($unitPrefix, ['ENG', 'CIV', 'MEC', 'ELE'])) {
            return $structures['engineering'];
        } elseif ($unitPrefix === 'MAT') {
            return $structures['mathematics'];
        } elseif ($unitLevel === '4' || str_contains($unitCode, '40')) {
            return $structures['capstone'];
        }

        // Default structure
        return $structures['foundation'];
    }
}
