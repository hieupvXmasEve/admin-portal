<?php

declare(strict_types=1);

namespace App\Services\Examples;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseRegistration;
use App\Models\GpaCalculation;
use App\Models\Student;
use App\Support\BusinessActionLogger;
use Spatie\Activitylog\Facades\LogBatch;

/**
 * Examples showing how to use BusinessActionLogger in various business scenarios
 *
 * These examples demonstrate best practices for logging complex business operations
 * that involve multiple models or calculations.
 */
class BusinessActionLoggerExamples
{
    /**
     * Example: Grade Entry with Complex Business Logic
     */
    public function processGradeEntry(int $scoreId, float $newScore, ?string $feedback = null): array
    {
        $score = AssessmentComponentDetailScore::findOrFail($scoreId);
        $oldScore = $score->achieved_score;

        return BusinessActionLogger::gradeEntry($score, 'update')
            ->withProperties([
                'old_score' => $oldScore,
                'new_score' => $newScore,
                'score_change' => $newScore - ($oldScore ?? 0),
                'has_feedback' => ! empty($feedback),
                'feedback_length' => $feedback ? strlen($feedback) : 0,
            ])
            ->execute(function () use ($score, $newScore, $feedback) {
                // Update the assessment score
                $score->update([
                    'achieved_score' => $newScore,
                    'feedback_comments' => $feedback,
                    'graded_at' => now(),
                    'graded_by_lecture_id' => auth()->user()->id,
                ]);

                // Recalculate course grade if needed
                $this->recalculateCourseGrade($score->student_code, $score->course_offering_id);

                // Check if this affects graduation requirements
                $graduationImpact = $this->checkGraduationImpact($score);

                return [
                    'score_updated' => true,
                    'course_grade_recalculated' => true,
                    'graduation_impact' => $graduationImpact,
                ];
            });
    }

    /**
     * Example: Bulk Grade Entry with Batch Logging
     */
    public function processBulkGradeEntry(array $gradeUpdates): array
    {
        $assessments = AssessmentComponentDetailScore::whereIn('id', array_column($gradeUpdates, 'id'))->get();

        return LogBatch::withinBatch(function () use ($gradeUpdates, $assessments) {
            return BusinessActionLogger::bulkGradeEntry($assessments->toArray(), 'update')
                ->withProperties([
                    'total_updates' => count($gradeUpdates),
                    'update_details' => $gradeUpdates,
                ])
                ->execute(function () use ($gradeUpdates, $assessments) {
                    $results = [];
                    $successCount = 0;
                    $errorCount = 0;

                    foreach ($gradeUpdates as $update) {
                        try {
                            $score = $assessments->firstWhere('id', $update['id']);
                            if (! $score) {
                                throw new \Exception("Assessment not found: {$update['id']}");
                            }

                            $score->update([
                                'achieved_score' => $update['score'],
                                'feedback_comments' => $update['feedback'] ?? null,
                                'graded_at' => now(),
                                'graded_by_lecture_id' => auth()->user()->id,
                            ]);

                            // Log individual grade entry within the batch
                            BusinessActionLogger::gradeEntry($score, 'update')
                                ->withProperties([
                                    'bulk_operation' => true,
                                    'old_score' => $score->getOriginal('achieved_score'),
                                    'new_score' => $update['score'],
                                ])
                                ->log();

                            $successCount++;
                        } catch (\Exception $e) {
                            $results['errors'][] = [
                                'id' => $update['id'],
                                'error' => $e->getMessage(),
                            ];
                            $errorCount++;
                        }
                    }

                    return [
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'total_processed' => count($gradeUpdates),
                        'errors' => $results['errors'] ?? [],
                    ];
                });
        });
    }

    /**
     * Example: Course Withdrawal with Financial Calculation
     */
    public function processCourseWithdrawal(int $registrationId, string $reason, bool $calculateRefund = true): array
    {
        $registration = CourseRegistration::with(['student', 'courseOffering'])->findOrFail($registrationId);
        $refundAmount = 0;

        if ($calculateRefund) {
            $refundAmount = $this->calculateRefundAmount($registration);
        }

        return BusinessActionLogger::courseWithdrawal($registration, $reason)
            ->withProperties([
                'calculate_refund' => $calculateRefund,
                'refund_amount' => $refundAmount,
                'withdrawal_date' => now()->toDateString(),
                'semester_progress' => $this->calculateSemesterProgress($registration->semester_id),
            ])
            ->execute(function () use ($registration, $reason, $refundAmount) {
                // Update registration status
                $registration->update([
                    'registration_status' => 'withdrawn',
                    'withdrawal_date' => now(),
                    'withdrawal_reason' => $reason,
                ]);

                // Update course offering capacity
                $registration->courseOffering->decrement('current_enrollment');

                // Process refund if applicable
                if ($refundAmount > 0) {
                    BusinessActionLogger::financial('refund', $registration->student, $refundAmount, 'course_withdrawal')
                        ->withProperties([
                            'related_registration_id' => $registration->id,
                            'course_code' => $registration->courseOffering->curriculumUnit->unit->code ?? 'Unknown',
                        ])
                        ->log();
                }

                // Check impact on graduation timeline
                $graduationImpact = $this->assessGraduationImpact($registration->student);

                return [
                    'registration_withdrawn' => true,
                    'refund_processed' => $refundAmount > 0,
                    'refund_amount' => $refundAmount,
                    'graduation_impact' => $graduationImpact,
                ];
            });
    }

    /**
     * Example: GPA Calculation with Academic Standing Update
     */
    public function calculateAndUpdateGPA(int $studentId, int $semesterId): array
    {
        $student = Student::findOrFail($studentId);

        return BusinessActionLogger::gpaCalculation($student, 'semester')
            ->withProperties([
                'semester_id' => $semesterId,
                'calculation_trigger' => 'manual',
            ])
            ->execute(function () use ($student, $semesterId) {
                // Get academic records for the semester
                $records = AcademicRecord::where('student_code', $student->id)
                    ->where('semester_id', $semesterId)
                    ->where('completion_status', 'completed')
                    ->get();

                if ($records->isEmpty()) {
                    throw new \Exception('No completed courses found for GPA calculation');
                }

                // Calculate semester GPA
                $totalQualityPoints = $records->sum('quality_points');
                $totalCreditHours = $records->sum('credit_hours');
                $semesterGPA = $totalCreditHours > 0 ? $totalQualityPoints / $totalCreditHours : 0;

                // Calculate cumulative GPA
                $allRecords = AcademicRecord::where('student_code', $student->id)
                    ->where('completion_status', 'completed')
                    ->get();

                $cumulativeQualityPoints = $allRecords->sum('quality_points');
                $cumulativeCreditHours = $allRecords->sum('credit_hours');
                $cumulativeGPA = $cumulativeCreditHours > 0 ? $cumulativeQualityPoints / $cumulativeCreditHours : 0;

                // Store GPA calculation
                $gpaCalculation = GpaCalculation::updateOrCreate(
                    [
                        'student_code' => $student->id,
                        'semester_id' => $semesterId,
                        'calculation_type' => 'semester',
                    ],
                    [
                        'gpa' => $semesterGPA,
                        'quality_points' => $totalQualityPoints,
                        'credit_hours_attempted' => $totalCreditHours,
                        'credit_hours_earned' => $totalCreditHours,
                        'total_courses' => $records->count(),
                        'completed_courses' => $records->count(),
                        'calculated_at' => now(),
                        'calculated_by_lecture_id' => auth()->user()?->id,
                        'is_current' => true,
                    ]
                );

                // Update cumulative GPA record
                GpaCalculation::updateOrCreate(
                    [
                        'student_code' => $student->id,
                        'calculation_type' => 'cumulative',
                    ],
                    [
                        'gpa' => $cumulativeGPA,
                        'quality_points' => $cumulativeQualityPoints,
                        'credit_hours_attempted' => $cumulativeCreditHours,
                        'credit_hours_earned' => $cumulativeCreditHours,
                        'total_courses' => $allRecords->count(),
                        'completed_courses' => $allRecords->count(),
                        'calculated_at' => now(),
                        'calculated_by_lecture_id' => auth()->user()?->id,
                        'is_current' => true,
                    ]
                );

                // Determine academic standing
                $oldAcademicStatus = $student->academic_status;
                $newAcademicStatus = $this->determineAcademicStanding($cumulativeGPA);

                if ($oldAcademicStatus !== $newAcademicStatus) {
                    $student->update(['academic_status' => $newAcademicStatus]);

                    // Log academic standing change
                    BusinessActionLogger::academicStanding($student, $oldAcademicStatus, $newAcademicStatus)
                        ->withProperties([
                            'trigger' => 'gpa_calculation',
                            'semester_gpa' => $semesterGPA,
                            'cumulative_gpa' => $cumulativeGPA,
                        ])
                        ->log();
                }

                return [
                    'semester_gpa' => round($semesterGPA, 3),
                    'cumulative_gpa' => round($cumulativeGPA, 3),
                    'academic_standing_changed' => $oldAcademicStatus !== $newAcademicStatus,
                    'old_academic_status' => $oldAcademicStatus,
                    'new_academic_status' => $newAcademicStatus,
                    'courses_included' => $records->count(),
                ];
            });
    }

    /**
     * Example: Graduation Evaluation Process
     */
    public function evaluateGraduationEligibility(int $studentId): array
    {
        $student = Student::with(['program', 'curriculumVersion'])->findOrFail($studentId);

        return BusinessActionLogger::graduationEvaluation($student)
            ->withProperties([
                'program_id' => $student->program_id,
                'curriculum_version_id' => $student->curriculum_version_id,
                'evaluation_date' => now()->toDateString(),
            ])
            ->execute(function () use ($student) {
                // Get all academic records
                $records = AcademicRecord::where('student_code', $student->id)
                    ->where('completion_status', 'completed')
                    ->with(['unit', 'semester'])
                    ->get();

                // Calculate totals
                $totalCreditsEarned = $records->sum('credit_hours_earned');
                $totalGPA = $this->calculateCumulativeGPA($student->id);

                // Get graduation requirements
                $requirements = $this->getGraduationRequirements($student);

                // Check each requirement
                $requirementChecks = [];
                $allRequirementsMet = true;

                foreach ($requirements as $requirement) {
                    $isMet = $this->checkGraduationRequirement($student, $requirement, $records);
                    $requirementChecks[] = [
                        'requirement' => $requirement['name'],
                        'required' => $requirement['value'],
                        'achieved' => $requirement['achieved_value'],
                        'is_met' => $isMet,
                    ];

                    if (! $isMet) {
                        $allRequirementsMet = false;
                    }
                }

                // Determine graduation eligibility
                $eligibilityStatus = $allRequirementsMet ? 'eligible' : 'not_eligible';
                $missingRequirements = collect($requirementChecks)->where('is_met', false)->values()->toArray();

                // Update student graduation status if eligible
                if ($allRequirementsMet && $student->graduation_status !== 'eligible') {
                    $student->update([
                        'graduation_status' => 'eligible',
                        'graduation_eligibility_date' => now(),
                    ]);
                }

                return [
                    'eligibility_status' => $eligibilityStatus,
                    'total_credits_earned' => $totalCreditsEarned,
                    'cumulative_gpa' => $totalGPA,
                    'requirements_checked' => count($requirementChecks),
                    'requirements_met' => count(array_filter($requirementChecks, fn ($r) => $r['is_met'])),
                    'missing_requirements' => $missingRequirements,
                    'can_graduate' => $allRequirementsMet,
                    'evaluation_summary' => $requirementChecks,
                ];
            });
    }

    /**
     * Example: Program Change with Transfer Credit Evaluation
     */
    public function processStudentProgramChange(int $studentId, int $newProgramId, ?string $reason = null): array
    {
        $student = Student::with('program')->findOrFail($studentId);
        $oldProgram = $student->program;
        $newProgram = \App\Models\Program::findOrFail($newProgramId);

        return BusinessActionLogger::programChange($student, $oldProgram, $newProgram)
            ->withProperties([
                'change_reason' => $reason,
                'change_date' => now()->toDateString(),
                'initiated_by' => auth()->user()->name ?? 'System',
            ])
            ->execute(function () use ($student, $oldProgram, $newProgram, $reason) {
                // Evaluate credit transfer
                $creditTransferAnalysis = $this->evaluateCreditTransfer($student, $oldProgram, $newProgram);

                // Update student program
                $student->update([
                    'program_id' => $newProgram->id,
                    'program_change_date' => now(),
                    'program_change_reason' => $reason,
                    'previous_program_id' => $oldProgram->id,
                ]);

                // Create academic record entries for transfer credits
                foreach ($creditTransferAnalysis['transferable_credits'] as $credit) {
                    AcademicRecord::create([
                        'student_code' => $student->id,
                        'unit_id' => $credit['unit_id'],
                        'program_id' => $newProgram->id,
                        'campus_id' => $student->campus_id,
                        'is_transfer_credit' => true,
                        'credit_hours' => $credit['credit_hours'],
                        'credit_hours_earned' => $credit['credit_hours'],
                        'final_letter_grade' => $credit['grade'],
                        'grade_points' => $credit['grade_points'],
                        'completion_status' => 'completed',
                        'completion_date' => now(),
                    ]);
                }

                // Update graduation timeline
                $this->updateGraduationTimeline($student, $newProgram);

                return [
                    'program_changed' => true,
                    'old_program' => $oldProgram->name,
                    'new_program' => $newProgram->name,
                    'credits_transferred' => $creditTransferAnalysis['transferred_count'],
                    'credits_lost' => $creditTransferAnalysis['lost_count'],
                    'additional_credits_needed' => $creditTransferAnalysis['additional_needed'],
                    'transfer_summary' => $creditTransferAnalysis,
                ];
            });
    }

    // Helper methods (these would be implemented based on your business logic)

    private function recalculateCourseGrade(int $studentId, int $courseOfferingId): void
    {
        // Implementation for recalculating course grade
    }

    private function checkGraduationImpact($score): array
    {
        // Implementation for checking graduation impact
        return ['affects_graduation' => false];
    }

    private function calculateRefundAmount(CourseRegistration $registration): float
    {
        // Implementation for calculating refund amount
        return 0.0;
    }

    private function calculateSemesterProgress(int $semesterId): float
    {
        // Implementation for calculating semester progress
        return 0.5;
    }

    private function assessGraduationImpact(Student $student): array
    {
        // Implementation for assessing graduation impact
        return ['timeline_affected' => false];
    }

    private function determineAcademicStanding(float $gpa): string
    {
        if ($gpa >= 3.5) {
            return 'excellent';
        }
        if ($gpa >= 3.0) {
            return 'good';
        }
        if ($gpa >= 2.0) {
            return 'satisfactory';
        }

        return 'probation';
    }

    private function calculateCumulativeGPA(int $studentId): float
    {
        // Implementation for calculating cumulative GPA
        return 3.0;
    }

    private function getGraduationRequirements(Student $student): array
    {
        // Implementation for getting graduation requirements
        return [];
    }

    private function checkGraduationRequirement(Student $student, array $requirement, $records): bool
    {
        // Implementation for checking individual graduation requirements
        return true;
    }

    private function evaluateCreditTransfer(Student $student, $oldProgram, $newProgram): array
    {
        // Implementation for evaluating credit transfer
        return [
            'transferable_credits' => [],
            'transferred_count' => 0,
            'lost_count' => 0,
            'additional_needed' => 0,
        ];
    }

    private function updateGraduationTimeline(Student $student, $newProgram): void
    {
        // Implementation for updating graduation timeline
    }
}
