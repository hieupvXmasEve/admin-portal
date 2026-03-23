<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class PublishCourseStageChangedNotificationAction
{
    public function __construct(
        private PublishDomainEventAction $publishDomainEventAction
    ) {}

    public function run(
        Student $student,
        string $fromStage,
        string $toStage,
        int $semesterId,
        ?string $message = null
    ): void {
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2', 'v2_only'], true)) {
            return;
        }

        if (! $student->user_id) {
            return;
        }

        $semester = Semester::find($semesterId);
        $semesterLabel = $semester?->code ?? ('Semester '.$semesterId);
        $body = $message;

        if ($body === null) {
            $body = $toStage === 'intake_course'
                ? "You have been admitted to your major from {$semesterLabel}."
                : "Your academic stage has been updated from {$semesterLabel}.";
        }

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'academic.course_stage_changed',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'student',
            aggregateId: (string) $student->id,
            campusId: (int) $student->campus_id,
            actorUserId: null,
            payload: [
                'type_key' => 'course_stage_changed',
                'channels' => ['realtime'],
                'recipient_targets' => [
                    ['type' => 'student', 'id' => (int) $student->id],
                ],
                'data' => [
                    'title' => $toStage === 'intake_course'
                        ? 'Major Admission Confirmed'
                        : 'Academic Stage Updated',
                    'body' => $body,
                    'category' => 'academic',
                    'is_important' => true,
                    'action_url' => '',
                    'action_text' => 'View Academic Records',
                    'from_course_stage' => $fromStage,
                    'to_course_stage' => $toStage,
                    'semester_id' => $semesterId,
                    'semester_code' => $semesterLabel,
                ],
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }
}
