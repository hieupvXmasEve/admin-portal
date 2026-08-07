<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Models\ScholarshipDefinition;
use App\Models\Student;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\AcademicLifecycleEventFactory;
use App\Shared\Contracts\Academic\ScholarshipReviewDeferralNotifier;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;

/**
 * Implementation for App\Shared\Contracts\Academic\ScholarshipReviewDeferralNotifier.
 * Finance only supplies student ids + semester; this action resolves the
 * in-flight dossier, student, and scholarship name itself so Finance never
 * needs Academic's models.
 */
class PublishTuitionDeferredNotificationAction implements ScholarshipReviewDeferralNotifier
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {}

    public function notifyTuitionDeferred(array $studentIds, int $targetSemesterId): void
    {
        if ($studentIds === []) {
            return;
        }

        // A student may legally have more than one in-flight dossier for the
        // same target semester (different source semesters) — pick the
        // oldest deterministically rather than an arbitrary DB-order row.
        $dossiers = ScholarshipAdjustmentDossier::query()
            ->whereIn('student_id', $studentIds)
            ->where('target_semester_id', $targetSemesterId)
            ->whereIn('status', ScholarshipAdjustmentDossier::IN_FLIGHT_STATUSES)
            ->with('targetSemester')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->first());

        $students = Student::query()->whereIn('id', $studentIds)->get()->keyBy('id');

        // The scholarship under review is the one named on the dossier itself
        // — not a separate, unordered lookup of the student's current awards,
        // which would be nondeterministic for a student with more than one.
        $scholarshipCodes = $dossiers->pluck('original_scholarship_code')->filter()->unique();
        $definitions = ScholarshipDefinition::query()
            ->whereIn('code', $scholarshipCodes)
            ->get()
            ->keyBy('code');

        foreach ($studentIds as $studentId) {
            $dossier = $dossiers->get($studentId);
            $student = $students->get($studentId);

            if ($dossier === null || $student === null) {
                continue;
            }

            $event = AcademicLifecycleEventFactory::scholarshipAdjustmentTuitionDeferred(
                $student,
                (int) $dossier->id,
                (int) $dossier->minutes_version,
                $dossier->targetSemester?->name,
                $definitions->get($dossier->original_scholarship_code)?->name,
            );

            $this->domainEventPublisher->publishAfterCommit($event);
        }
    }
}
