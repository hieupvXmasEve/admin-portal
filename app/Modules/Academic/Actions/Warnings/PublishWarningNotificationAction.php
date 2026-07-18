<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Warnings;

use App\Models\Student;
use App\Models\StudentWarningLog;
use App\Modules\Academic\Support\AcademicLifecycleEventFactory;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;

class PublishWarningNotificationAction
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {}

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $data
     */
    public function run(StudentWarningLog $log, Student $student, array $channels, array $data): string
    {
        $event = AcademicLifecycleEventFactory::warningSent($log, $student, $channels, $data);
        $this->domainEventPublisher->publishAfterCommit($event);

        return $event->eventId();
    }
}
