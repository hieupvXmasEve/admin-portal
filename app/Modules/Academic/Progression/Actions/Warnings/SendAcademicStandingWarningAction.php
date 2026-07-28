<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\Warnings;

use App\Models\AcademicWarningSetting;
use App\Models\GpaCalculation;
use App\Models\StudentWarningLog;
use App\Models\User;
use App\Modules\Academic\Support\WarningDedupe;
use App\Modules\Academic\Support\WarningMessageRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendAcademicStandingWarningAction
{
    public function __construct(
        private readonly PublishWarningNotificationAction $publishWarningNotification,
    ) {}

    /**
     * @return array{sent: bool, duplicate: bool, message: string, log: StudentWarningLog}
     */
    public function run(GpaCalculation $gpaCalculation, User $actor, AcademicWarningSetting $settings): array
    {
        $gpaCalculation->loadMissing(['student.program', 'student.intakeSemester', 'student.intakeMajorSemester', 'semester']);
        $student = $gpaCalculation->student;

        if (! $student || (float) $gpaCalculation->cumulative_gpa >= 50.0 || $gpaCalculation->academic_standing !== 'warning') {
            throw ValidationException::withMessages([
                'student' => 'Student is not currently in academic standing warning.',
            ]);
        }

        $dedupeKey = WarningDedupe::academicStanding((int) $student->id, (int) $gpaCalculation->id);
        $channels = $settings->activeChannels();
        $variables = [
            'student_name' => $student->full_name,
            'student_id' => $student->student_id,
            'cumulative_gpa' => number_format((float) $gpaCalculation->cumulative_gpa, 2),
            'credits_earned' => number_format((float) $gpaCalculation->cumulative_credit_points_earned, 2),
            'credits_attempted' => number_format((float) $gpaCalculation->cumulative_credit_points, 2),
            'semester' => $gpaCalculation->semester?->code,
        ];
        $body = WarningMessageRenderer::render($settings->academic_warning_body, $variables);

        return DB::transaction(function () use ($dedupeKey, $gpaCalculation, $student, $actor, $settings, $channels, $body, $variables) {
            $log = StudentWarningLog::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'warning_type' => StudentWarningLog::TYPE_ACADEMIC_STANDING,
                    'campus_id' => $student->campus_id,
                    'student_id' => $student->id,
                    'gpa_calculation_id' => $gpaCalculation->id,
                    'threshold_snapshot' => [
                        'academic_standing_threshold' => 50,
                        'cumulative_gpa' => (float) $gpaCalculation->cumulative_gpa,
                    ],
                    'channels' => $channels,
                    'message_title' => $settings->academic_warning_title,
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
                    'message' => 'Academic standing warning was already sent for this GPA calculation.',
                    'log' => $log,
                ];
            }

            $eventId = $this->publishWarningNotification->run($log, $student, $channels, [
                ...$variables,
                'gpa_calculation_id' => (int) $gpaCalculation->id,
                'action_url' => '/dashboard',
                'action_text' => 'View dashboard',
            ]);

            $log->forceFill(['notification_event_id' => $eventId])->save();

            return [
                'sent' => true,
                'duplicate' => false,
                'message' => 'Academic standing warning has been queued for in-app and email delivery.',
                'log' => $log,
            ];
        });
    }
}
