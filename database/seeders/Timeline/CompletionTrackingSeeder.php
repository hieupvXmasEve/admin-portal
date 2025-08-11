<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\GpaCalculation;
use App\Models\Student;
use Illuminate\Database\Seeder;

class CompletionTrackingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Tracks degree completion progress for all students
     */
    public function run(): void
    {
        $this->command->info('📈 Tracking degree completion progress...');

        // Get all students with academic records
        $students = Student::whereHas('academicRecords')->with([
            'academicRecords' => function ($query) {
                $query->where('grade_status', 'final');
            },
            'curriculumVersion.curriculumUnits.unit',
        ])->get();

        if ($students->isEmpty()) {
            throw new \Exception('No students with academic records found.');
        }

        $completionStats = [
            'total_students' => $students->count(),
            'on_track' => 0,
            'ahead_of_schedule' => 0,
            'behind_schedule' => 0,
            'at_risk' => 0,
            'near_graduation' => 0,
        ];

        foreach ($students as $student) {
            $completionData = $this->analyzeStudentCompletion($student);
            $this->updateStudentCompletionStatus($student, $completionData);

            // Update statistics
            $completionStats[$completionData['status']]++;
        }

        $this->displayCompletionStatistics($completionStats);
    }

    private function analyzeStudentCompletion(Student $student): array
    {
        // Get curriculum requirements
        $totalRequiredUnits = $student->curriculumVersion->curriculumUnits->count();
        $totalRequiredCredits = $student->curriculumVersion->curriculumUnits->sum('unit.credit_points');

        // Get completed units
        $completedRecords = $student->academicRecords->where('completion_status', 'completed');
        $completedUnits = $completedRecords->count();
        $completedCredits = $completedRecords->sum('credit_hours');

        // Calculate completion percentages
        $unitCompletionPercentage = $totalRequiredUnits > 0 ? ($completedUnits / $totalRequiredUnits) * 100 : 0;
        $creditCompletionPercentage = $totalRequiredCredits > 0 ? ($completedCredits / $totalRequiredCredits) * 100 : 0;

        // Get current GPA
        $latestGPA = GpaCalculation::where('student_id', $student->id)
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        $currentGPA = $latestGPA ? (float) $latestGPA->gpa : 0.0;

        // Analyze completion by category
        $completionByCategory = $this->analyzeCompletionByCategory($student);

        // Determine expected progress (simplified - based on semesters enrolled)
        $semestersEnrolled = $this->calculateSemestersEnrolled($student);
        $expectedCompletionPercentage = min(($semestersEnrolled / 8) * 100, 100); // Assuming 8 semester degree

        // Determine completion status
        $status = $this->determineCompletionStatus(
            $unitCompletionPercentage,
            $expectedCompletionPercentage,
            $currentGPA,
            $completedCredits,
            $totalRequiredCredits
        );

        return [
            'total_required_units' => $totalRequiredUnits,
            'total_required_credits' => $totalRequiredCredits,
            'completed_units' => $completedUnits,
            'completed_credits' => $completedCredits,
            'unit_completion_percentage' => round($unitCompletionPercentage, 1),
            'credit_completion_percentage' => round($creditCompletionPercentage, 1),
            'expected_completion_percentage' => round($expectedCompletionPercentage, 1),
            'current_gpa' => $currentGPA,
            'semesters_enrolled' => $semestersEnrolled,
            'completion_by_category' => $completionByCategory,
            'status' => $status,
            'estimated_graduation_semester' => $this->estimateGraduationSemester($unitCompletionPercentage, $semestersEnrolled),
        ];
    }

    private function analyzeCompletionByCategory(Student $student): array
    {
        $categories = ['core', 'elective', 'major', 'minor'];
        $completionByCategory = [];

        foreach ($categories as $category) {
            $requiredUnits = $student->curriculumVersion->curriculumUnits
                ->where('unit_type', $category);

            $completedUnits = $student->academicRecords
                ->where('completion_status', 'completed')
                ->whereIn('unit_id', $requiredUnits->pluck('unit_id'));

            $completionByCategory[$category] = [
                'required' => $requiredUnits->count(),
                'completed' => $completedUnits->count(),
                'percentage' => $requiredUnits->count() > 0 ?
                    round(($completedUnits->count() / $requiredUnits->count()) * 100, 1) : 0,
            ];
        }

        return $completionByCategory;
    }

    private function calculateSemestersEnrolled(Student $student): int
    {
        // Count distinct semesters where student has academic records
        return $student->academicRecords
            ->pluck('semester_id')
            ->unique()
            ->count();
    }

    private function determineCompletionStatus(
        float $unitCompletion,
        float $expectedCompletion,
        float $gpa,
        float $completedCredits,
        float $totalCredits
    ): string {
        // Near graduation (90%+ complete)
        if ($unitCompletion >= 90) {
            return 'near_graduation';
        }

        // At risk (low GPA or significantly behind)
        if ($gpa < 2.0 || ($unitCompletion < $expectedCompletion - 20)) {
            return 'at_risk';
        }

        // Ahead of schedule
        if ($unitCompletion > $expectedCompletion + 10) {
            return 'ahead_of_schedule';
        }

        // Behind schedule
        if ($unitCompletion < $expectedCompletion - 10) {
            return 'behind_schedule';
        }

        // On track
        return 'on_track';
    }

    private function estimateGraduationSemester(float $completionPercentage, int $semestersEnrolled): string
    {
        if ($completionPercentage >= 90) {
            return 'Current semester or next';
        }

        // Estimate based on current progress rate
        $progressRate = $semestersEnrolled > 0 ? $completionPercentage / $semestersEnrolled : 0;

        if ($progressRate > 0) {
            $remainingPercentage = 100 - $completionPercentage;
            $estimatedSemestersRemaining = ceil($remainingPercentage / $progressRate);

            if ($estimatedSemestersRemaining <= 2) {
                return 'Within 1 year';
            } elseif ($estimatedSemestersRemaining <= 4) {
                return 'Within 2 years';
            } else {
                return 'More than 2 years';
            }
        }

        return 'Unable to estimate';
    }

    private function updateStudentCompletionStatus(Student $student, array $completionData): void
    {
        // Create comprehensive completion notes
        $completionNotes = $this->generateCompletionNotes($completionData);

        // Update student record
        $currentNotes = $student->admission_notes ?? '';
        $student->update([
            'admission_notes' => trim($currentNotes . ' | ' . $completionNotes),
        ]);

        // Log significant milestones
        if ($completionData['status'] === 'near_graduation') {
            $this->command->info("  🎓 {$student->student_id}: Near graduation ({$completionData['unit_completion_percentage']}% complete)");
        } elseif ($completionData['status'] === 'at_risk') {
            $this->command->info("  ⚠️ {$student->student_id}: At risk (GPA: {$completionData['current_gpa']}, {$completionData['unit_completion_percentage']}% complete)");
        } elseif ($completionData['status'] === 'ahead_of_schedule') {
            $this->command->info("  🚀 {$student->student_id}: Ahead of schedule ({$completionData['unit_completion_percentage']}% complete)");
        }
    }

    private function generateCompletionNotes(array $data): string
    {
        $notes = [];

        $notes[] = "Completion: {$data['unit_completion_percentage']}% units, {$data['credit_completion_percentage']}% credits";
        $notes[] = 'Status: ' . str_replace('_', ' ', ucwords($data['status']));
        $notes[] = "GPA: {$data['current_gpa']}";
        $notes[] = "Est. graduation: {$data['estimated_graduation_semester']}";

        // Add category-specific notes
        foreach ($data['completion_by_category'] as $category => $info) {
            if ($info['required'] > 0) {
                $notes[] = ucfirst($category) . ": {$info['completed']}/{$info['required']} ({$info['percentage']}%)";
            }
        }

        return implode(' | ', $notes);
    }

    private function displayCompletionStatistics(array $stats): void
    {
        $this->command->info('✅ Degree completion tracking complete!');
        $this->command->info("  📊 Total students analyzed: {$stats['total_students']}");
        $this->command->info("  ✅ On track: {$stats['on_track']} students");
        $this->command->info("  🚀 Ahead of schedule: {$stats['ahead_of_schedule']} students");
        $this->command->info("  📉 Behind schedule: {$stats['behind_schedule']} students");
        $this->command->info("  ⚠️ At risk: {$stats['at_risk']} students");
        $this->command->info("  🎓 Near graduation: {$stats['near_graduation']} students");
    }
}
