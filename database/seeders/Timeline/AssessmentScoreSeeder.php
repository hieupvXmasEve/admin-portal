<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseRegistration;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class AssessmentScoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates assessment scores for FALL2024 students
     */
    public function run(): void
    {
        $this->command->info('📊 Creating assessment scores for FALL2024...');

        // Get FALL2024 semester
        $semester = Semester::where('code', 'FALL2024')->first();

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        // Get all course registrations for FALL2024
        $registrations = CourseRegistration::where('semester_id', $semester->id)
            ->where('registration_status', 'registered')
            ->with(['student', 'courseOffering.unit'])
            ->get();

        if ($registrations->isEmpty()) {
            throw new \Exception('No course registrations found for FALL2024.');
        }

        // Clean existing scores
        AssessmentComponentDetailScore::whereHas('assessmentComponentDetail.component.syllabus', function ($query) use ($semester) {
            $query->where('semester_id', $semester->id);
        })->delete();

        $scoreCount = 0;

        foreach ($registrations as $registration) {
            $scores = $this->createScoresForRegistration($registration);
            $scoreCount += count($scores);

            if ($scoreCount % 100 === 0) {
                $this->command->info("  Created {$scoreCount} assessment scores...");
            }
        }

        $this->command->info("✅ Created {$scoreCount} assessment scores for FALL2024!");
    }

    private function createScoresForRegistration(CourseRegistration $registration): array
    {
        $scores = [];

        // Get assessment component details for this course offering
        $assessmentDetails = AssessmentComponentDetail::whereHas('component.syllabus', function ($query) use ($registration) {
            $query->where('unit_id', $registration->courseOffering->unit_id)
                ->where('semester_id', $registration->semester_id);
        })->with(['component'])->get();

        foreach ($assessmentDetails as $detail) {
            $score = $this->generateScoreForStudent($registration, $detail);

            $assessmentScore = AssessmentComponentDetailScore::create([
                'assessment_component_detail_id' => $detail->id,
                'student_id' => $registration->student_id,
                'course_offering_id' => $registration->course_offering_id,
                'graded_by_lecture_id' => $registration->courseOffering->instructor_id,
                'points_earned' => $score['score'],
                'percentage_score' => $score['percentage'],
                'submitted_at' => $score['submission_date'],
                'graded_at' => $score['graded_date'],
                'submission_attempt' => 1,
                'instructor_feedback' => $score['feedback'],
                'is_late' => $score['late_submission'],
                'late_penalty_applied' => $score['late_penalty'],
                'status' => 'graded',
                'score_status' => 'final',
                'private_notes' => $score['notes'],
            ]);

            $scores[] = $assessmentScore;
        }

        return $scores;
    }

    private function generateScoreForStudent(CourseRegistration $registration, AssessmentComponentDetail $detail): array
    {
        $component = $detail->component;
        $student = $registration->student;

        // Generate realistic scores based on assessment type and student performance
        $basePerformance = $this->getStudentBasePerformance($student);
        $assessmentType = $component->type;

        // Adjust performance based on assessment type
        $performanceModifier = $this->getAssessmentTypeModifier($assessmentType);
        $adjustedPerformance = $basePerformance + $performanceModifier;

        // Add some randomness
        $randomVariation = rand(-10, 10);
        $finalPerformance = max(0, min(100, $adjustedPerformance + $randomVariation));

        // Calculate actual score
        $maxScore = 100; // Most assessments are out of 100
        $actualScore = round(($finalPerformance / 100) * $maxScore, 1);

        // Determine submission timing
        $submissionTiming = $this->getSubmissionTiming($assessmentType);

        return [
            'score' => $actualScore,
            'max_score' => $maxScore,
            'percentage' => round($finalPerformance, 1),
            'submission_date' => $submissionTiming['submission_date'],
            'graded_date' => $submissionTiming['graded_date'],
            'feedback' => $this->generateFeedback($finalPerformance, $assessmentType),
            'late_submission' => $submissionTiming['late_submission'],
            'late_penalty' => $submissionTiming['late_penalty'],
            'notes' => $submissionTiming['late_submission'] ? 'Late submission penalty applied' : null,
        ];
    }

    private function getStudentBasePerformance($student): float
    {
        // Generate consistent performance based on student characteristics
        // Use student ID as seed for consistency
        $seed = (int) substr($student->student_id, -3);
        srand($seed);

        // Most students perform in the 60-85 range
        $performance = rand(60, 85);

        // Some high performers (15%)
        if (rand(1, 100) <= 15) {
            $performance = rand(85, 95);
        }

        // Some struggling students (10%)
        if (rand(1, 100) <= 10) {
            $performance = rand(40, 65);
        }

        // Reset random seed
        srand();

        return $performance;
    }

    private function getAssessmentTypeModifier(string $assessmentType): float
    {
        // Different assessment types have different difficulty patterns
        $modifiers = [
            'assignment' => 5,    // Assignments tend to score higher
            'project' => 3,       // Projects are generally well-scored
            'quiz' => 0,          // Quizzes are neutral
            'exam' => -5,         // Exams tend to be more challenging
            'other' => 2,         // Other assessments slightly easier
        ];

        return $modifiers[$assessmentType] ?? 0;
    }

    private function getSubmissionTiming(string $assessmentType): array
    {
        $baseDate = now()->subDays(rand(1, 30)); // Random date in the past month

        // Determine if submission is late (10% chance)
        $isLate = rand(1, 100) <= 10;

        if ($isLate) {
            $lateDays = rand(1, 5);
            $submissionDate = $baseDate->copy()->addDays($lateDays);
            $latePenalty = min(20, $lateDays * 5); // 5% penalty per day, max 20%
        } else {
            $submissionDate = $baseDate->copy()->subDays(rand(0, 2)); // On time or early
            $latePenalty = 0;
        }

        // Grading happens 3-7 days after submission
        $gradedDate = $submissionDate->copy()->addDays(rand(3, 7));

        return [
            'submission_date' => $submissionDate->toDateString(),
            'graded_date' => $gradedDate->toDateString(),
            'late_submission' => $isLate,
            'late_penalty' => $latePenalty,
        ];
    }

    private function generateFeedback(float $percentage, string $assessmentType): string
    {
        if ($percentage >= 85) {
            $feedbacks = [
                'Excellent work! Demonstrates thorough understanding of the concepts.',
                'Outstanding performance. Clear evidence of mastery.',
                'Exceptional quality. Well-structured and comprehensive.',
                'Excellent analysis and application of course material.',
            ];
        } elseif ($percentage >= 75) {
            $feedbacks = [
                'Good work overall. Shows solid understanding with minor areas for improvement.',
                'Well done. Good grasp of the material with some room for enhancement.',
                'Solid performance. Demonstrates competency in most areas.',
                'Good effort. Clear understanding with some minor gaps.',
            ];
        } elseif ($percentage >= 65) {
            $feedbacks = [
                'Satisfactory work. Meets basic requirements but could be improved.',
                'Adequate performance. Shows understanding but needs more depth.',
                'Acceptable work. Consider reviewing key concepts for better understanding.',
                'Fair effort. Some areas need more attention and development.',
            ];
        } elseif ($percentage >= 50) {
            $feedbacks = [
                'Below expectations. Please review the material and seek additional help.',
                'Needs improvement. Consider attending consultation hours.',
                'Requires more effort. Please review feedback carefully.',
                'Unsatisfactory. Recommend additional study and practice.',
            ];
        } else {
            $feedbacks = [
                'Significant improvement needed. Please see instructor for support.',
                'Well below standard. Immediate intervention required.',
                'Major concerns with understanding. Please seek academic support.',
                'Requires substantial improvement. Consider additional resources.',
            ];
        }

        return $feedbacks[array_rand($feedbacks)];
    }
}
