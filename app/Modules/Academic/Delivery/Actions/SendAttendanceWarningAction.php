<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AcademicWarningSetting;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\StudentWarningLog;
use App\Models\User;
use App\Modules\Academic\Queries\ListWarningCenterQuery;
use App\Modules\Academic\Support\WarningDedupe;
use App\Modules\Academic\Support\WarningMessageRenderer;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendAttendanceWarningAction
{
    public function __construct(
        private readonly PublishWarningNotificationAction $publishWarningNotification,
        private readonly ListWarningCenterQuery $warningCenterQuery,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    /**
     * @return array{sent: bool, duplicate: bool, message: string, log: StudentWarningLog}
     */
    public function run(CourseOffering $courseOffering, Student $student, User $actor, AcademicWarningSetting $settings): array
    {
        $currentPeriodId = $this->academicPeriods->current()?->id;
        if ($currentPeriodId === null || (int) $courseOffering->semester_id !== $currentPeriodId) {
            throw ValidationException::withMessages([
                'course_offering' => 'Attendance warnings can only be sent for sections in the active semester.',
            ]);
        }

        $courseOffering->load([
            'unit:id,code,name',
            'syllabusTemplate:id,min_attendance_threshold,total_sessions',
            'classSessions' => function ($query) {
                $query->orderBy('session_date')->orderBy('start_time');
            },
            'classSessions.attendances' => fn ($query) => $query->where('student_id', $student->id),
        ]);

        $isRegistered = $courseOffering->courseRegistrations()
            ->where('student_id', $student->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->exists();

        if (! $isRegistered) {
            throw ValidationException::withMessages([
                'student' => 'Student is not registered in this section.',
            ]);
        }

        $sessions = $courseOffering->classSessions;
        $totalSessions = $sessions->count();
        $minAttendance = (float) ($courseOffering->syllabusTemplate?->min_attendance_threshold ?? 80.00);
        $allowedAbsences = $this->warningCenterQuery->allowedAbsences($totalSessions, $minAttendance);
        $warningAbsences = $this->warningCenterQuery->warningAbsences(
            $totalSessions,
            $minAttendance,
            (float) $settings->attendance_warning_ratio,
        );

        $absentSessions = $sessions->filter(function ($session) use ($student) {
            return $session->attendances
                ->where('student_id', $student->id)
                ->where('status', 'absent')
                ->isNotEmpty();
        })->values();

        $absenceCount = $absentSessions->count();
        if ($totalSessions === 0 || $absenceCount <= $warningAbsences) {
            throw ValidationException::withMessages([
                'attendance' => 'Student has not reached the attendance warning threshold for this section.',
            ]);
        }

        $latestAbsentSession = $absentSessions->last();
        if (! $latestAbsentSession) {
            throw ValidationException::withMessages([
                'attendance' => 'No absent class session could be found for this warning.',
            ]);
        }

        $warningType = $absenceCount > $allowedAbsences
            ? StudentWarningLog::TYPE_ATTENDANCE_EXCEEDED
            : StudentWarningLog::TYPE_ATTENDANCE_EARLY;

        $dedupeKey = WarningDedupe::attendance(
            $warningType,
            (int) $student->id,
            (int) $courseOffering->id,
            (int) $latestAbsentSession->id,
            $absenceCount,
        );
        $channels = $settings->activeChannels();
        $attendanceRate = $totalSessions > 0 ? round((($totalSessions - $absenceCount) / $totalSessions) * 100, 2) : 0.0;
        $sectionCode = $courseOffering->section_code ?: '.1';

        $variables = [
            'student_name' => $student->full_name,
            'student_id' => $student->student_id,
            'course_code' => $courseOffering->unit?->code,
            'course_name' => $courseOffering->unit?->name,
            'section_code' => $sectionCode,
            'absence_count' => $absenceCount,
            'warning_absences' => $warningAbsences,
            'allowed_absences' => $allowedAbsences,
            'total_sessions' => $totalSessions,
            'attendance_rate' => number_format($attendanceRate, 2),
            'min_attendance_threshold' => number_format($minAttendance, 2),
            'latest_absent_date' => $latestAbsentSession->session_date?->format('Y-m-d'),
        ];

        $title = $warningType === StudentWarningLog::TYPE_ATTENDANCE_EXCEEDED
            ? $settings->attendance_exceeded_title
            : $settings->attendance_warning_title;
        $template = $warningType === StudentWarningLog::TYPE_ATTENDANCE_EXCEEDED
            ? $settings->attendance_exceeded_body
            : $settings->attendance_warning_body;
        $body = WarningMessageRenderer::render($template, $variables);

        $attendanceWarningRatio = (float) $settings->attendance_warning_ratio;

        return DB::transaction(function () use ($dedupeKey, $warningType, $courseOffering, $student, $actor, $channels, $title, $body, $variables, $absenceCount, $warningAbsences, $allowedAbsences, $totalSessions, $minAttendance, $attendanceWarningRatio, $latestAbsentSession) {
            $log = StudentWarningLog::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'warning_type' => $warningType,
                    'campus_id' => $student->campus_id,
                    'student_id' => $student->id,
                    'course_offering_id' => $courseOffering->id,
                    'class_session_id' => $latestAbsentSession->id,
                    'absence_count' => $absenceCount,
                    'warning_absences' => $warningAbsences,
                    'allowed_absences' => $allowedAbsences,
                    'total_sessions' => $totalSessions,
                    'threshold_snapshot' => [
                        'min_attendance_threshold' => $minAttendance,
                        'attendance_warning_ratio' => $attendanceWarningRatio,
                        'trigger_rule' => 'absence_count > warning_absences',
                    ],
                    'channels' => $channels,
                    'message_title' => $title,
                    'message_body' => $body,
                    'status' => 'queued',
                    'actor_user_id' => $actor->id,
                    'sent_at' => now(),
                ],
            );

            if (! $log->wasRecentlyCreated) {
                return [
                    'sent' => false,
                    'duplicate' => true,
                    'message' => 'Attendance warning was already sent for this absence milestone.',
                    'log' => $log,
                ];
            }

            $eventId = $this->publishWarningNotification->run($log, $student, $channels, [
                ...$variables,
                'course_offering_id' => (int) $courseOffering->id,
                'class_session_id' => (int) $latestAbsentSession->id,
                'action_url' => '/attendance-report',
                'action_text' => 'View attendance',
            ]);

            $log->forceFill(['notification_event_id' => $eventId])->save();

            return [
                'sent' => true,
                'duplicate' => false,
                'message' => 'Attendance warning has been queued for in-app and email delivery.',
                'log' => $log,
            ];
        });
    }
}
