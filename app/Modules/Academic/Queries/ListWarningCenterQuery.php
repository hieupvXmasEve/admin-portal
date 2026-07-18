<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseOffering;
use App\Models\GpaCalculation;
use App\Models\StudentWarningLog;
use App\Modules\Academic\Support\WarningDedupe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListWarningCenterQuery
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function academicStandingWarnings(array $filters, ?int $campusId): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'cumulative_gpa';
        $direction = $filters['direction'] ?? 'asc';

        $sorts = [
            'cumulative_gpa' => 'cumulative_gpa',
            'credits_earned' => 'cumulative_credit_points_earned',
            'credits_attempted' => 'cumulative_credit_points',
            'updated_at' => 'updated_at',
        ];

        $query = GpaCalculation::query()
            ->with([
                'student:id,student_id,full_name,email,campus_id,program_id,intake_semester_id,intake_major,intake',
                'student.program:id,name,code',
                'student.intakeSemester:id,code,name',
                'student.intakeMajorSemester:id,code,name',
                'semester:id,code,name',
            ])
            ->where('is_current', true)
            ->where('academic_standing', 'warning')
            ->where('cumulative_gpa', '<', 50)
            ->when($campusId, function (Builder $query) use ($campusId) {
                $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId));
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->whereHas('student', function (Builder $studentQuery) use ($search) {
                    $studentQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sorts[$sort] ?? 'cumulative_gpa', $direction === 'desc' ? 'desc' : 'asc');

        $paginator = $query
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $dedupeKeys = $paginator->getCollection()
            ->map(fn (GpaCalculation $calculation) => WarningDedupe::academicStanding((int) $calculation->student_id, (int) $calculation->id))
            ->all();

        $logs = StudentWarningLog::query()
            ->whereIn('dedupe_key', $dedupeKeys)
            ->get(['dedupe_key', 'sent_at', 'status'])
            ->keyBy('dedupe_key');

        $paginator->getCollection()->transform(function (GpaCalculation $calculation) use ($logs): array {
            $student = $calculation->student;
            $dedupeKey = WarningDedupe::academicStanding((int) $calculation->student_id, (int) $calculation->id);
            $log = $logs->get($dedupeKey);

            return [
                'id' => (int) $calculation->id,
                'dedupe_key' => $dedupeKey,
                'student' => [
                    'id' => (int) $student->id,
                    'name' => (string) $student->full_name,
                    'student_id' => (string) $student->student_id,
                    'email' => $student->email,
                    'program' => $student->program?->code ?? $student->program?->name,
                ],
                'intake' => $student->intakeMajorSemester?->code
                    ?? $student->intakeSemester?->code
                    ?? ($student->intake ? (string) $student->intake : null),
                'semester' => $calculation->semester?->code,
                'current_cumulative_gpa' => round((float) $calculation->cumulative_gpa, 2),
                'total_credits_earned' => round((float) $calculation->cumulative_credit_points_earned, 2),
                'total_credits_attempted' => round((float) $calculation->cumulative_credit_points, 2),
                'academic_standing' => (string) $calculation->academic_standing,
                'warning_sent_at' => $log?->sent_at?->toISOString(),
                'warning_status' => $log?->status,
            ];
        });

        return $paginator;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attendanceWarnings(?int $campusId, ?int $academicPeriodId, float $warningRatio = 0.50): array
    {
        if ($academicPeriodId === null) {
            return [];
        }

        $offerings = CourseOffering::query()
            ->with([
                'unit:id,code,name,credit_points',
                'semester:id,code,name',
                'lecture:id,first_name,last_name',
                'syllabusTemplate:id,min_attendance_threshold,total_sessions',
                'classSessions' => function ($query) {
                    $query->orderBy('session_date')->orderBy('start_time');
                },
                'classSessions.attendances:id,class_session_id,student_id,status',
                'courseRegistrations' => function ($query) {
                    $query->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
                        ->with([
                            'student:id,student_id,full_name,email,campus_id,intake_semester_id,intake_major,intake',
                            'student.intakeSemester:id,code,name',
                            'student.intakeMajorSemester:id,code,name',
                        ]);
                },
            ])
            ->where('semester_id', $academicPeriodId)
            ->when($campusId, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->where('is_active', true)
            ->orderBy('unit_id')
            ->orderBy('section_code')
            ->get();

        $subjects = [];
        $dedupeKeys = [];

        foreach ($offerings as $offering) {
            $unit = $offering->unit;
            if (! $unit) {
                continue;
            }

            $sessions = $offering->classSessions;
            $totalSessions = $sessions->count();
            $minAttendance = (float) ($offering->syllabusTemplate?->min_attendance_threshold ?? 80.00);
            $allowedAbsences = $this->allowedAbsences($totalSessions, $minAttendance);
            $warningAbsences = $this->warningAbsences($totalSessions, $minAttendance, $warningRatio);

            [$absencesByStudent, $latestAbsentSessionByStudent] = $this->absenceMaps($sessions);

            $warningStudents = [];

            foreach ($offering->courseRegistrations as $registration) {
                $student = $registration->student;
                if (! $student) {
                    continue;
                }

                $absenceCount = (int) ($absencesByStudent[$student->id] ?? 0);
                if ($totalSessions === 0 || $absenceCount <= $warningAbsences) {
                    continue;
                }

                $latestSessionId = (int) ($latestAbsentSessionByStudent[$student->id]['id'] ?? 0);
                if ($latestSessionId <= 0) {
                    continue;
                }

                $warningType = $absenceCount > $allowedAbsences
                    ? StudentWarningLog::TYPE_ATTENDANCE_EXCEEDED
                    : StudentWarningLog::TYPE_ATTENDANCE_EARLY;

                $dedupeKey = WarningDedupe::attendance(
                    $warningType,
                    (int) $student->id,
                    (int) $offering->id,
                    $latestSessionId,
                    $absenceCount,
                );
                $dedupeKeys[] = $dedupeKey;

                $warningStudents[] = [
                    'dedupe_key' => $dedupeKey,
                    'warning_type' => $warningType,
                    'student' => [
                        'id' => (int) $student->id,
                        'name' => (string) $student->full_name,
                        'student_id' => (string) $student->student_id,
                        'email' => $student->email,
                    ],
                    'intake' => $student->intakeMajorSemester?->code
                        ?? $student->intakeSemester?->code
                        ?? ($student->intake ? (string) $student->intake : null),
                    'absence_count' => $absenceCount,
                    'warning_absences' => $warningAbsences,
                    'allowed_absences' => $allowedAbsences,
                    'total_sessions' => $totalSessions,
                    'attendance_rate' => $totalSessions > 0 ? round((($totalSessions - $absenceCount) / $totalSessions) * 100, 2) : 0.0,
                    'latest_absent_session' => $latestAbsentSessionByStudent[$student->id] ?? null,
                    'status' => $absenceCount > $allowedAbsences ? 'limit_exceeded' : 'warning',
                    'warning_sent_at' => null,
                    'warning_status' => null,
                ];
            }

            $subjectKey = (string) $unit->id;
            $subjects[$subjectKey] ??= [
                'id' => (int) $unit->id,
                'code' => (string) $unit->code,
                'name' => (string) $unit->name,
                'sections' => [],
                'warning_count' => 0,
            ];

            $subjects[$subjectKey]['sections'][] = [
                'id' => (int) $offering->id,
                'section_code' => $offering->section_code ?: '.1',
                'course_code' => (string) $unit->code,
                'course_name' => (string) $unit->name,
                'lecture_name' => $offering->lecture ? trim($offering->lecture->first_name.' '.$offering->lecture->last_name) : null,
                'total_sessions' => $totalSessions,
                'min_attendance_threshold' => $minAttendance,
                'warning_absences' => $warningAbsences,
                'allowed_absences' => $allowedAbsences,
                'warning_count' => count($warningStudents),
                'students' => $warningStudents,
            ];
            $subjects[$subjectKey]['warning_count'] += count($warningStudents);
        }

        $logs = StudentWarningLog::query()
            ->whereIn('dedupe_key', array_values(array_unique($dedupeKeys)))
            ->get(['dedupe_key', 'sent_at', 'status'])
            ->keyBy('dedupe_key');

        foreach ($subjects as &$subject) {
            foreach ($subject['sections'] as &$section) {
                foreach ($section['students'] as &$studentWarning) {
                    $log = $logs->get($studentWarning['dedupe_key']);
                    $studentWarning['warning_sent_at'] = $log?->sent_at?->toISOString();
                    $studentWarning['warning_status'] = $log?->status;
                }
                unset($studentWarning);
            }
            unset($section);
        }
        unset($subject);

        return array_values($subjects);
    }

    public function allowedAbsences(int $totalSessions, float $minAttendanceThreshold): int
    {
        $allowedAbsenceRatio = max(0.0, (100.0 - $minAttendanceThreshold) / 100.0);

        return (int) floor($totalSessions * $allowedAbsenceRatio);
    }

    public function warningAbsences(int $totalSessions, float $minAttendanceThreshold, float $warningRatio = 0.50): int
    {
        $allowedAbsenceRatio = max(0.0, (100.0 - $minAttendanceThreshold) / 100.0);
        $warningAbsenceRatio = $allowedAbsenceRatio * max(0.0, min(1.0, $warningRatio));

        return (int) floor($totalSessions * $warningAbsenceRatio);
    }

    /**
     * @param  Collection<int, mixed>  $sessions
     * @return array{0: array<int, int>, 1: array<int, array{id:int,date:string|null,sequence_number:int|null}>}
     */
    private function absenceMaps(Collection $sessions): array
    {
        $absencesByStudent = [];
        $latestAbsentSessionByStudent = [];

        foreach ($sessions as $session) {
            foreach ($session->attendances as $attendance) {
                if ($attendance->status !== 'absent') {
                    continue;
                }

                $studentId = (int) $attendance->student_id;
                $absencesByStudent[$studentId] = ($absencesByStudent[$studentId] ?? 0) + 1;
                $latestAbsentSessionByStudent[$studentId] = [
                    'id' => (int) $session->id,
                    'date' => $session->session_date?->format('Y-m-d'),
                    'sequence_number' => $session->sequence_number ? (int) $session->sequence_number : null,
                ];
            }
        }

        return [$absencesByStudent, $latestAbsentSessionByStudent];
    }
}
