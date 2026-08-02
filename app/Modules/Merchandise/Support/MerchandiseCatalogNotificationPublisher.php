<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Models\Merchandise;
use App\Models\Student;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Fires a broadcast notification when a new catalog item goes live, through
 * the same Shared Contract domain-event seam as RedemptionNotificationPublisher
 * (this module never imports App\Modules\Notification directly —
 * DomainBoundaryArchitectureTest forbids it). In-app only (realtime), no
 * campus scope — Merchandise itself carries no campus_id (only its variants
 * do), so every active student is a plausible buyer.
 */
class MerchandiseCatalogNotificationPublisher
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly NotificationPayloadFactory $payloadFactory,
    ) {}

    public function catalogItemPublished(Merchandise $merchandise): void
    {
        $studentIds = Student::query()->active()->pluck('id');

        if ($studentIds->isEmpty()) {
            return;
        }

        $targets = $studentIds->map(fn (int $id) => ['type' => 'student', 'id' => $id])->all();

        $payload = $this->payloadFactory->build('merchandise_catalog_item_published', [
            'title' => 'New item in the Merchandise Store',
            'body' => "{$merchandise->name} is now available for {$merchandise->gold_price} Gold.",
            'action_type' => 'merchandise.catalog_item',
            'action_params' => ['id' => $merchandise->id],
        ]);

        $event = new DomainEvent(
            name: 'merchandise.catalog_item_published',
            // A merchandise item can only ever be *created* once, so its id
            // alone is a stable, safe dedupe key for this event type.
            deduplicationKey: 'merchandise_catalog_item_published:'.$merchandise->id,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'merchandise',
            aggregateId: (string) $merchandise->id,
            campusId: null,
            actorUserId: Auth::id(),
            payload: [
                'type_key' => 'merchandise_catalog_item_published',
                'recipient_targets' => $targets,
                'channels' => ['realtime'],
                'data' => $payload,
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }
}
