<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Warnings;

use App\Models\Student;
use App\Models\StudentWarningLog;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class PublishWarningNotificationAction
{
    public function __construct(
        private readonly PublishDomainEventAction $publishDomainEvent,
    ) {}

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $data
     */
    public function run(StudentWarningLog $log, Student $student, array $channels, array $data): string
    {
        $eventId = (string) Str::uuid();

        $this->publishDomainEvent->run(new DomainEventEnvelope(
            eventId: $eventId,
            eventName: 'academic.warning_sent',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'student_warning_log',
            aggregateId: (string) $log->id,
            campusId: $log->campus_id ?? $student->campus_id,
            actorUserId: $log->actor_user_id,
            payload: [
                'type_key' => $log->warning_type,
                'channels' => $channels,
                'recipient_targets' => [
                    ['type' => 'student', 'id' => (int) $student->id],
                ],
                'priority' => $log->warning_type === StudentWarningLog::TYPE_ATTENDANCE_EXCEEDED ? 'high' : 'normal',
                'data' => [
                    ...$data,
                    'title' => $log->message_title,
                    'body' => $log->message_body,
                    'warning_log_id' => (int) $log->id,
                    'warning_type' => $log->warning_type,
                    'category' => str_starts_with($log->warning_type, 'attendance') ? 'attendance' : 'academic',
                    'is_important' => true,
                ],
            ],
        ));

        return $eventId;
    }
}
