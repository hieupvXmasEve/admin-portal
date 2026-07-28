<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Support\AcademicLifecycleEventFactory;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;

class PublishCourseStageChangedNotificationAction
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {}

    public function run(
        Student $student,
        string $fromStage,
        string $toStage,
        int $semesterId,
        ?string $message = null
    ): void {
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

        $this->domainEventPublisher->publishAfterCommit(
            AcademicLifecycleEventFactory::courseStageChanged(
                $student,
                $fromStage,
                $toStage,
                $semesterId,
                $semesterLabel,
                $body,
            ),
        );
    }
}
