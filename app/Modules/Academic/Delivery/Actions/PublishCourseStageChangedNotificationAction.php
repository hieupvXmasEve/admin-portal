<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\Student;
use App\Modules\Academic\Progression\Support\AcademicLifecycleEventFactory;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;

class PublishCourseStageChangedNotificationAction
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly AcademicPeriodReader $academicPeriods,
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

        $semesterLabel = $this->academicPeriods->find($semesterId)?->code ?? ('Semester '.$semesterId);
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
