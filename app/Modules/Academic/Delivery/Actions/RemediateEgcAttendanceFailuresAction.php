<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\OperationalAttendanceService;
use App\Modules\Academic\Support\FailureReasonClassifier;
use App\Shared\Contracts\Finance\EgcBlockResultReconciler;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RemediateEgcAttendanceFailuresAction
{
    /**
     * @return array{
     *     candidates: int,
     *     remediated: int,
     *     skipped: int,
     *     attendances_flipped: int,
     *     semesters_synced: int,
     *     details: list<array<string, mixed>>
     * }
     */
    public static function run(
        bool $dryRun = false,
        ?string $studentIdentifier = null,
        ?int $courseOfferingId = null,
    ): array {
        $records = self::candidateQuery($studentIdentifier, $courseOfferingId)->get();

        if ($dryRun) {
            return self::remediateRecords($records, true);
        }

        return DB::transaction(fn (): array => self::remediateRecords($records, false));
    }

    /**
     * @param  Collection<int, AcademicRecord>  $records
     * @return array{
     *     candidates: int,
     *     remediated: int,
     *     skipped: int,
     *     attendances_flipped: int,
     *     semesters_synced: int,
     *     details: list<array<string, mixed>>
     * }
     */
    private static function remediateRecords(Collection $records, bool $dryRun): array
    {
        $stats = [
            'candidates' => $records->count(),
            'remediated' => 0,
            'skipped' => 0,
            'attendances_flipped' => 0,
            'semesters_synced' => 0,
            'details' => [],
        ];

        $affectedSemesterIds = [];

        foreach ($records as $record) {
            $result = self::remediateRecord($record, $dryRun);

            $stats['details'][] = $result;

            if ($result['status'] === 'remediated') {
                $stats['remediated']++;
                $stats['attendances_flipped'] += $result['attendances_flipped'];

                if (! $dryRun) {
                    $affectedSemesterIds[$record->semester_id] = true;
                }
            } else {
                $stats['skipped']++;
            }
        }

        if (! $dryRun && $affectedSemesterIds !== []) {
            foreach (array_keys($affectedSemesterIds) as $semesterId) {
                app(EgcBlockResultReconciler::class)->reconcileSemester((int) $semesterId);
                $stats['semesters_synced']++;
            }
        }

        if (! $dryRun && $stats['remediated'] > 0) {
            Log::info('EGC attendance remediation completed', $stats);
        }

        return $stats;
    }

    /**
     * @return Builder<AcademicRecord>
     */
    private static function candidateQuery(?string $studentIdentifier, ?int $courseOfferingId): Builder
    {
        $query = AcademicRecord::query()
            ->select('academic_records.*')
            ->join('course_offerings', 'academic_records.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->join('semesters', 'academic_records.semester_id', '=', 'semesters.id')
            ->join('syllabus_templates', 'course_offerings.syllabus_template_id', '=', 'syllabus_templates.id')
            ->where('units.unit_type', 'egc')
            ->where('semesters.is_active', true)
            ->where('academic_records.is_passed', false)
            ->where('academic_records.override_pass', false)
            ->whereNull('academic_records.deleted_at')
            ->whereColumn('academic_records.final_percentage', '>=', 'syllabus_templates.min_grade_threshold')
            ->where(function (Builder $builder): void {
                $builder
                    ->where('academic_records.meets_attendance_requirement', false)
                    ->orWhereColumn('academic_records.attendance_percentage', '<', 'syllabus_templates.min_attendance_threshold');
            })
            ->with(['student', 'unit', 'courseOffering.syllabusTemplate']);

        if ($courseOfferingId !== null) {
            $query->where('academic_records.course_offering_id', $courseOfferingId);
        }

        if ($studentIdentifier !== null) {
            $query->whereIn('academic_records.student_id', self::studentIdsFor($studentIdentifier));
        }

        return $query;
    }

    /**
     * Resolve the operator-supplied identifier, which may be a student code or a
     * raw id, to the ids it can mean. Both are accepted because the command is
     * driven by hand.
     *
     * @return list<int>
     */
    private static function studentIdsFor(string $studentIdentifier): array
    {
        $registry = app(StudentReferenceReader::class);

        $ids = [];

        if (($byCode = $registry->findByStudentCodeAnywhere($studentIdentifier)) !== null) {
            $ids[] = $byCode->id;
        }

        if (is_numeric($studentIdentifier) && ($byId = $registry->find((int) $studentIdentifier)) !== null) {
            $ids[] = $byId->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>
     */
    private static function remediateRecord(AcademicRecord $record, bool $dryRun): array
    {
        $courseOffering = $record->courseOffering;
        $syllabus = $courseOffering?->syllabusTemplate;

        if ($courseOffering === null || $syllabus === null) {
            return self::detail($record, 'skipped', 'Missing course offering or syllabus template', 0, null, null);
        }

        $gradeThreshold = (float) ($syllabus->min_grade_threshold ?? 70);
        $attendanceThreshold = (float) ($syllabus->min_attendance_threshold ?? 80);
        $finalPercentage = (float) ($record->final_percentage ?? 0);

        if ($finalPercentage < $gradeThreshold) {
            return self::detail($record, 'skipped', 'Grade below threshold', 0, null, null);
        }

        $counts = self::attendanceCounts($record, $courseOffering);
        $absentsToFlip = self::minimumAbsencesToFlip(
            $counts['present'],
            $counts['late'],
            $counts['absent'],
            $attendanceThreshold,
        );

        if ($absentsToFlip === 0) {
            return self::detail(
                $record,
                'skipped',
                'No absent sessions to flip or attendance already meets threshold',
                0,
                $counts['attendance_percentage'],
                $counts['attendance_percentage'],
            );
        }

        $absentAttendances = self::selectAbsentAttendances($record, $courseOffering, $absentsToFlip);

        if ($absentAttendances->count() < $absentsToFlip) {
            return self::detail(
                $record,
                'skipped',
                'Insufficient absent attendance rows',
                0,
                $counts['attendance_percentage'],
                null,
            );
        }

        $projectedCounts = [
            'present' => $counts['present'] + $absentsToFlip,
            'late' => $counts['late'],
            'absent' => $counts['absent'] - $absentsToFlip,
            'not_recorded' => $counts['not_recorded'],
            'total_sessions' => $counts['total_sessions'],
        ];

        $projectedPercentage = self::attendancePercentage(
            $projectedCounts['present'],
            $projectedCounts['late'],
            $projectedCounts['absent'],
        );

        if ($dryRun) {
            return self::detail(
                $record,
                'remediated',
                'Dry run — would flip absent sessions to present',
                $absentsToFlip,
                $counts['attendance_percentage'],
                $projectedPercentage,
            );
        }

        $affectedSessionIds = $absentAttendances->pluck('class_session_id')->unique()->values();

        DB::transaction(function () use ($record, $absentAttendances, $affectedSessionIds, $projectedCounts, $gradeThreshold, $attendanceThreshold, $finalPercentage): void {
            Attendance::withoutEvents(function () use ($absentAttendances): void {
                foreach ($absentAttendances as $attendance) {
                    $attendance->update([
                        'status' => 'present',
                        'check_in_time' => $attendance->check_in_time ?? now(),
                    ]);
                }
            });

            self::refreshSessionAttendanceStatistics($affectedSessionIds);

            $eval = FailureReasonClassifier::classify(
                $finalPercentage,
                $gradeThreshold,
                $projectedCounts['present'],
                $projectedCounts['late'],
                $projectedCounts['absent'],
                $projectedCounts['not_recorded'],
                $projectedCounts['total_sessions'],
                $attendanceThreshold,
                false,
                false,
            );

            $cleanNotes = $record->administrative_notes;
            if ($cleanNotes && str_contains($cleanNotes, 'FAILED: Attendance requirement not met')) {
                $cleanNotes = trim((string) preg_replace('/^FAILED: Attendance requirement not met.*$/m', '', $cleanNotes));
            }

            $creditPoints = (float) $record->credit_points > 0
                ? (float) $record->credit_points
                : (float) $record->credit_hours;

            $record->update([
                'total_present' => $projectedCounts['present'],
                'total_late' => $projectedCounts['late'],
                'total_absences' => $projectedCounts['absent'],
                'total_not_recorded' => $projectedCounts['not_recorded'],
                'total_class_sessions' => $projectedCounts['total_sessions'],
                'attendance_percentage' => $eval['snapshot']['attendance_pct'],
                'meets_attendance_requirement' => true,
                'is_passed' => $eval['is_passed'],
                'failure_reason' => $eval['failure_reason'],
                'failure_reason_snapshot' => $eval['snapshot'],
                'credit_points_earned' => $eval['is_passed'] ? $creditPoints : 0,
                'credit_hours_earned' => $eval['is_passed'] ? $record->credit_hours : 0,
                'satisfies_prerequisite' => $eval['is_passed'],
                'administrative_notes' => $cleanNotes !== '' ? $cleanNotes : null,
            ]);
        });

        return self::detail(
            $record,
            'remediated',
            'Flipped absent sessions to present',
            $absentsToFlip,
            $counts['attendance_percentage'],
            $projectedPercentage,
        );
    }

    /**
     * @return array{
     *     present: int,
     *     late: int,
     *     absent: int,
     *     excused: int,
     *     not_recorded: int,
     *     total_sessions: int,
     *     attendance_percentage: float|null
     * }
     */
    private static function attendanceCounts(AcademicRecord $record, CourseOffering $courseOffering): array
    {
        $sessionIds = ClassSession::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereNull('deleted_at')
            ->pluck('id');

        $attendances = Attendance::query()
            ->where('student_id', $record->student_id)
            ->whereIn('class_session_id', $sessionIds)
            ->whereNull('deleted_at')
            ->get();

        $present = $attendances->where('status', 'present')->count();
        $late = $attendances->where('status', 'late')->count();
        $absent = $attendances->where('status', 'absent')->count();
        $excused = $attendances->where('status', 'excused')->count();
        $totalSessions = $sessionIds->count();
        $notRecorded = max(0, $totalSessions - ($present + $late + $absent + $excused));

        return [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $excused,
            'not_recorded' => $notRecorded,
            'total_sessions' => $totalSessions,
            'attendance_percentage' => self::attendancePercentage($present, $late, $absent),
        ];
    }

    private static function attendancePercentage(int $present, int $late, int $absent): ?float
    {
        $recorded = $present + $late + $absent;

        if ($recorded === 0) {
            return null;
        }

        return round((($present + $late) / $recorded) * 100, 2);
    }

    private static function minimumAbsencesToFlip(
        int $present,
        int $late,
        int $absent,
        float $attendanceThreshold,
    ): int {
        $recorded = $present + $late + $absent;

        if ($recorded === 0 || $absent === 0) {
            return 0;
        }

        $attended = $present + $late;
        $currentPercentage = ($attended / $recorded) * 100;

        if ($currentPercentage >= $attendanceThreshold) {
            return 0;
        }

        $requiredAttended = (int) ceil(($attendanceThreshold / 100) * $recorded);

        return max(0, min($requiredAttended - $attended, $absent));
    }

    /**
     * @param  Collection<int, int>  $sessionIds
     */
    private static function refreshSessionAttendanceStatistics(Collection $sessionIds): void
    {
        if ($sessionIds->isEmpty()) {
            return;
        }

        $attendanceService = app(OperationalAttendanceService::class);

        ClassSession::query()
            ->whereIn('id', $sessionIds->all())
            ->get()
            ->each(fn (ClassSession $session) => $attendanceService->updateAttendanceStatistics($session));
    }

    /**
     * @return Collection<int, Attendance>
     */
    private static function selectAbsentAttendances(
        AcademicRecord $record,
        CourseOffering $courseOffering,
        int $limit,
    ): Collection {
        return Attendance::query()
            ->select('attendances.*')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('attendances.student_id', $record->student_id)
            ->where('class_sessions.course_offering_id', $courseOffering->id)
            ->where('attendances.status', 'absent')
            ->whereNull('attendances.deleted_at')
            ->whereNull('class_sessions.deleted_at')
            ->orderByDesc('class_sessions.session_date')
            ->orderByDesc('class_sessions.id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private static function detail(
        AcademicRecord $record,
        string $status,
        string $message,
        int $attendancesFlipped,
        ?float $attendanceBefore,
        ?float $attendanceAfter,
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'academic_record_id' => $record->id,
            'student_code' => $record->student?->student_id,
            'student_name' => $record->student?->full_name,
            'unit_code' => $record->unit?->code,
            'semester_id' => $record->semester_id,
            'attendances_flipped' => $attendancesFlipped,
            'attendance_before' => $attendanceBefore,
            'attendance_after' => $attendanceAfter,
        ];
    }
}
