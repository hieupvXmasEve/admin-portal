<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Models\Student;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\AcademicLifecycleEventFactory;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;

/**
 * Tells the student a confirmation is waiting for them. Kept separate from
 * RequestConfirmationAction (which owns the confirmation columns) so the state
 * write and the outbound notification stay independently testable — same split
 * as PublishWarningNotificationAction in the warnings flow.
 */
class PublishConfirmationRequestNotificationAction
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {}

    public function run(ScholarshipAdjustmentDossier $dossier): ?string
    {
        $student = Student::query()->find($dossier->student_id);

        if ($student === null) {
            return null;
        }

        $event = AcademicLifecycleEventFactory::scholarshipAdjustmentConfirmationRequested(
            $student,
            (int) $dossier->id,
            (int) $dossier->minutes_version,
            $dossier->targetSemester?->name,
        );

        $this->domainEventPublisher->publishAfterCommit($event);

        return $event->eventId();
    }
}
