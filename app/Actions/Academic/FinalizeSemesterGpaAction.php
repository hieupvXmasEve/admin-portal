<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Support\Academic\GpaValueComparator;
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
        $semester = Semester::findOrFail($semesterId);

        // Eligibility is decided by having a final, credit-bearing record for the
        // semester (below), not by the student's current status — a student who
        // completed the semester as intake_course and later deferred/withdrew
        // must still be finalizable for that past semester.
        $studentsQuery = Student::query();
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
            $semester,
            $semesterId,
            $adminId,
            &$processed,
            &$skippedPending,
            &$skippedNoCredit,
            &$skippedAlreadyFinal
        ) {
            foreach ($students as $student) {
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

                $recomputed = GpaValueComparator::recomputedRow(
                    $semesterData,
                    $cumulativeData,
                    $standing,
                    $student->program_id,
                );

                $existing = GpaCalculation::where('student_id', $student->id)
                    ->where('semester_id', $semesterId)
                    ->first();

                // Semantic skip: an already-finalized row whose stored values
                // still equal the recomputed ones needs no write. This replaces
                // the old positional "a finalized row exists" guard, which made
                // re-finalize impossible even when grades had changed.
                if ($existing !== null
                    && $existing->is_finalized
                    && GpaValueComparator::matches($existing->getAttributes(), $recomputed)) {
                    $skippedAlreadyFinal++;
                    continue;
                }

                // Claim is_current only when this is the student's latest
                // finalized semester by start_date; re-finalizing an earlier
                // semester must not pull the flag back from a later one.
                $claimCurrent = ! $this->hasLaterFinalizedSemester(
                    (int) $student->id,
                    $semesterId,
                    $semester->start_date,
                );

                if ($claimCurrent) {
                    GpaCalculation::where('student_id', $student->id)
                        ->where('is_current', true)
                        ->update(['is_current' => false]);
                }

                $priorSemesterGpa = $existing?->semester_gpa;
                $priorCumulativeGpa = $existing?->cumulative_gpa;

                // updateOrCreate keyed on the business key finds the live row and
                // updates it, so it does not attempt a second (student_id,
                // semester_id) insert. Assumes no soft-deleted GpaCalculation row
                // exists for the key: the unique index excludes deleted_at, so a
                // trashed row would push this to INSERT and violate the index. No
                // code path soft-deletes GpaCalculation today; revisit with
                // withTrashed()+restore if one is ever added.
                $row = GpaCalculation::updateOrCreate(
                    ['student_id' => $student->id, 'semester_id' => $semesterId],
                    array_merge($recomputed, [
                        'is_finalized' => true,
                        'finalized_at' => Carbon::now(),
                        'finalized_by_id' => $adminId,
                        'is_current' => $claimCurrent,
                    ]),
                );

                // In-place update overwrites the only snapshot, so this entry is
                // the sole record that the official numbers moved. Mandatory on
                // every re-finalize of a pre-existing row.
                if ($existing !== null) {
                    activity('gpa_refinalized')
                        ->causedBy(User::find($adminId))
                        ->performedOn($row)
                        ->withProperties([
                            'student_id' => (int) $student->id,
                            'semester_id' => (int) $semesterId,
                            'gpa_calculation_id' => (int) $row->id,
                            'old' => [
                                'semester_gpa' => $priorSemesterGpa,
                                'cumulative_gpa' => $priorCumulativeGpa,
                            ],
                            'new' => [
                                'semester_gpa' => $semesterData['gpa'],
                                'cumulative_gpa' => $cumulativeData['gpa'],
                            ],
                        ])
                        ->log("GPA re-finalized: student={$student->id}, semester={$semesterId}");
                }

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

    /**
     * Whether the student has a finalized GPA for a semester that starts strictly
     * later than the one being finalized. A NULL start_date is never "later", so
     * an undated semester can neither steal is_current nor be stolen from.
     *
     * Ties (two finalized semesters sharing a start_date) are not "later" than
     * each other, so re-finalizing either claims is_current — the most recently
     * finalized of the tied semesters wins.
     */
    private function hasLaterFinalizedSemester(int $studentId, int|string $semesterId, ?Carbon $thisStart): bool
    {
        return GpaCalculation::query()
            ->where('student_id', $studentId)
            ->where('semester_id', '!=', $semesterId)
            ->where('is_finalized', true)
            ->whereHas('semester', function ($query) use ($thisStart) {
                $query->whereNotNull('start_date');
                if ($thisStart !== null) {
                    $query->where('start_date', '>', $thisStart);
                }
                // $thisStart === null: this semester is undated, so any dated
                // finalized semester ranks after it and we do not claim is_current.
            })
            ->exists();
    }
}
