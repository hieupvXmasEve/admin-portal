<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinalizeSemesterGpaAction
{
    public function __construct(
        protected CalculateStudentSemesterGpaAction $calculateSemesterGpa,
        protected CalculateCumulativeGpaAction $calculateCumulativeGpa,
        protected DetermineAcademicStandingAction $determineStanding
    ) {}

    /**
     * Finalize GPA for all students in a semester.
     *
     * @return array{
     *     success: bool,
     *     processed_students: int,
     *     skipped: array{pending_grades: int, no_credits: int, already_finalized: int},
     *     message: string
     * }
     */
    public function execute(int|string $semesterId, int $adminId, int|string|null $campusId = null): array
    {
        Semester::findOrFail($semesterId);

        $studentsQuery = Student::where('status', 'intake_course');
        if ($campusId) {
            $studentsQuery->where('campus_id', $campusId);
        }

        $students = $studentsQuery
            ->whereHas('academicRecords', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId)
                    ->where('excluded_from_gpa', false)
                    ->where('credit_points', '>', 0);
            })
            ->with(['academicRecords' => function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            }])
            ->get();

        $processed = 0;
        $skippedPending = 0;
        $skippedNoCredit = 0;
        $skippedAlreadyFinal = 0;

        DB::transaction(function () use (
            $students,
            $semesterId,
            $adminId,
            &$processed,
            &$skippedPending,
            &$skippedNoCredit,
            &$skippedAlreadyFinal
        ) {
            foreach ($students as $student) {
                $alreadyFinalized = GpaCalculation::where('student_id', $student->id)
                    ->where('semester_id', $semesterId)
                    ->where('is_finalized', true)
                    ->exists();
                if ($alreadyFinalized) {
                    $skippedAlreadyFinal++;
                    continue;
                }

                $hasNonFinal = $student->academicRecords
                    ->where('grade_status', '!=', 'final')
                    ->isNotEmpty();
                if ($hasNonFinal) {
                    $skippedPending++;
                    continue;
                }

                $semesterData = $this->calculateSemesterGpa->execute($student, $semesterId);
                if ($semesterData['credit_points'] <= 0) {
                    $skippedNoCredit++;
                    continue;
                }

                $cumulativeData = $this->calculateCumulativeGpa->execute($student, $semesterId);
                $standing = $this->determineStanding->execute($cumulativeData['gpa']);

                GpaCalculation::where('student_id', $student->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                GpaCalculation::create([
                    'student_id' => $student->id,
                    'semester_id' => $semesterId,
                    'program_id' => $student->program_id,
                    'semester_gpa' => $semesterData['gpa'],
                    'cumulative_gpa' => $cumulativeData['gpa'],
                    'semester_quality_points' => $semesterData['quality_points'],
                    'cumulative_quality_points' => $cumulativeData['quality_points'],
                    'semester_credit_points' => $semesterData['credit_points'],
                    'cumulative_credit_points' => $cumulativeData['credit_points'],
                    'semester_credit_points_earned' => $semesterData['credit_points_earned'],
                    'cumulative_credit_points_earned' => $cumulativeData['credit_points_earned'],
                    'academic_standing' => $standing,
                    'is_finalized' => true,
                    'finalized_at' => Carbon::now(),
                    'finalized_by_id' => $adminId,
                    'is_current' => true,
                ]);

                $processed++;
            }
        });

        $totalSkipped = $skippedPending + $skippedNoCredit + $skippedAlreadyFinal;

        activity('gpa_finalization')
            ->causedBy(User::find($adminId))
            ->withProperties([
                'semester_id' => (int) $semesterId,
                'campus_id' => $campusId !== null ? (int) $campusId : null,
                'processed' => $processed,
                'skipped' => [
                    'pending_grades' => $skippedPending,
                    'no_credits' => $skippedNoCredit,
                    'already_finalized' => $skippedAlreadyFinal,
                ],
                'standing_threshold' => DetermineAcademicStandingAction::NORMAL_STANDING_THRESHOLD,
            ])
            ->log("GPA finalized: semester={$semesterId}, campus=" . ($campusId ?? 'all') . ", processed={$processed}");

        if ($processed === 0 && $skippedAlreadyFinal > 0) {
            return [
                'success' => false,
                'processed_students' => 0,
                'skipped' => [
                    'pending_grades' => $skippedPending,
                    'no_credits' => $skippedNoCredit,
                    'already_finalized' => $skippedAlreadyFinal,
                ],
                'message' => "Semester already finalized for all eligible students ({$skippedAlreadyFinal}). No changes were made.",
            ];
        }

        return [
            'success' => true,
            'processed_students' => $processed,
            'skipped' => [
                'pending_grades' => $skippedPending,
                'no_credits' => $skippedNoCredit,
                'already_finalized' => $skippedAlreadyFinal,
            ],
            'message' => "Successfully finalized GPA for {$processed} students. {$totalSkipped} students were skipped "
                . "(pending: {$skippedPending}, no credits: {$skippedNoCredit}, already finalized: {$skippedAlreadyFinal}).",
        ];
    }
}
