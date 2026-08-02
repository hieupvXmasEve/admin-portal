<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use Carbon\CarbonImmutable;

/**
 * Tells staff when a scholarship adjustment needs them.
 *
 * Separate from AcademicLifecycleEventFactory, which addresses the student: the
 * recipients here are resolved from who holds the relevant permission AT the
 * dossier's campus, the same rule RedemptionNotificationPublisher uses. Adding
 * a staff member to the right role is therefore all it takes to have them
 * notified — no code change, no per-feature recipient list.
 *
 * In-app only. These are work queues for staff who are already in the system,
 * and the student-facing confirmation request is the one that must chase
 * someone outside it.
 *
 * Crosses modules only through Shared contracts (domain-event seam), never by
 * importing App\Modules\Notification — DomainBoundaryArchitectureTest forbids it.
 */
class ScholarshipStaffNotificationPublisher
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly NotificationPayloadFactory $payloadFactory,
        private readonly CampusPermissionReader $permissions,
    ) {}

    /**
     * The student rejected the minutes. Whoever drives the dossier has to act:
     * correct the notes, or have an approver overrule. Until then the money
     * decision is blocked, and nothing else would surface that.
     */
    public function disputed(ScholarshipAdjustmentDossier $dossier, ?string $studentComment): void
    {
        $name = $this->studentLabel($dossier);
        $comment = $studentComment !== null && $studentComment !== ''
            ? " Ý kiến: \"{$studentComment}\""
            : '';

        $this->publish(
            'scholarship_adjustment_disputed',
            $dossier,
            'decide_scholarship_adjustment',
            [
                'title' => 'Sinh viên không đồng ý biên bản',
                'body' => "{$name} không đồng ý với biên bản xét học bổng.{$comment}",
            ],
        );
    }

    /**
     * The student accepted the minutes. That is what unblocks a fee-increasing
     * decision, so the people who can now act have to know it arrived —
     * otherwise a confirmed dossier sits waiting on staff who are still
     * expecting an answer.
     */
    public function confirmed(ScholarshipAdjustmentDossier $dossier, ?string $studentComment): void
    {
        $name = $this->studentLabel($dossier);
        $comment = $studentComment !== null && $studentComment !== ''
            ? " Ý kiến: \"{$studentComment}\""
            : '';

        $this->publish(
            'scholarship_adjustment_confirmed',
            $dossier,
            'decide_scholarship_adjustment',
            [
                'title' => 'Sinh viên đã xác nhận biên bản',
                'body' => "{$name} đồng ý với biên bản xét học bổng. Hồ sơ có thể ra quyết định.{$comment}",
            ],
        );
    }

    /**
     * The confirmation window closed with no answer. The overdue state is what
     * lets an approver override the gate, so it must not change silently.
     */
    public function confirmationOverdue(ScholarshipAdjustmentDossier $dossier): void
    {
        $name = $this->studentLabel($dossier);

        $this->publish(
            'scholarship_adjustment_confirmation_overdue',
            $dossier,
            'decide_scholarship_adjustment',
            [
                'title' => 'Quá hạn xác nhận học bổng',
                'body' => "{$name} không phản hồi biên bản trong thời hạn. Hồ sơ cần được xử lý tiếp.",
            ],
        );
    }

    /** A decision is proposed and waiting on someone who can approve it. */
    public function readyForDecision(ScholarshipAdjustmentDossier $dossier): void
    {
        $name = $this->studentLabel($dossier);

        $this->publish(
            'scholarship_adjustment_ready_for_decision',
            $dossier,
            'approve_scholarship_adjustment',
            [
                'title' => 'Quyết định học bổng chờ duyệt',
                'body' => "Đề xuất điều chỉnh học bổng của {$name} đang chờ phê duyệt.",
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function publish(
        string $typeKey,
        ScholarshipAdjustmentDossier $dossier,
        string $permissionCode,
        array $data,
    ): void {
        $campusId = (int) $dossier->campus_id;

        $userIds = $this->permissions->userIdsWithPermissionAtCampus($permissionCode, $campusId);

        if ($userIds === []) {
            return;
        }

        $payload = $this->payloadFactory->build($typeKey, array_merge($data, [
            'action_url' => "/scholarship-adjustments/{$dossier->id}",
            'action_params' => ['id' => $dossier->id],
            'dossier_id' => (int) $dossier->id,
        ]));

        $event = new DomainEvent(
            name: 'academic.'.$typeKey,
            // The minutes version is part of the key so a re-issued dispute on
            // corrected notes counts as a new event rather than being deduped
            // against the original one.
            deduplicationKey: $typeKey.':'.$dossier->id.':v'.(int) $dossier->minutes_version,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'scholarship_adjustment_dossier',
            aggregateId: (string) $dossier->id,
            campusId: $campusId,
            actorUserId: null,
            payload: [
                'type_key' => $typeKey,
                'recipient_targets' => array_map(
                    static fn (int $id): array => ['type' => 'user', 'id' => $id],
                    $userIds,
                ),
                'channels' => ['realtime'],
                'data' => $payload,
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }

    private function studentLabel(ScholarshipAdjustmentDossier $dossier): string
    {
        $student = $dossier->student;

        if ($student === null) {
            return 'Sinh viên';
        }

        return trim((string) $student->full_name).' ('.$student->student_id.')';
    }
}
