<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Actions\RetryDeliveryAction;
use App\Modules\Notification\Actions\RetryOutboxAction;
use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationMessageStatus;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Queries\ListDeliveriesQuery;
use App\Modules\Notification\Queries\ListMessagesQuery;
use App\Modules\Notification\Queries\ListOutboxQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationOpsController extends Controller
{
    private const ALLOWED_OUTBOX_SORTS = ['occurred_at', 'event_name', 'status', 'attempts', 'created_at'];

    private const ALLOWED_DELIVERY_SORTS = ['created_at', 'channel', 'status', 'attempts', 'queued_at', 'sent_at'];

    private const ALLOWED_MESSAGE_SORTS = ['created_at', 'type_key', 'status', 'read_at'];

    public function __construct(
        private readonly ListOutboxQuery $outboxQuery,
        private readonly ListDeliveriesQuery $deliveriesQuery,
        private readonly ListMessagesQuery $messagesQuery,
    ) {}

    public function outbox(Request $request): Response
    {
        $this->authorize('view_any_notification');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'event_name' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'has_error' => ['nullable', 'string', 'in:yes,no'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? 'occurred_at', self::ALLOWED_OUTBOX_SORTS, true)
            ? ($validated['sort'] ?? 'occurred_at')
            : 'occurred_at';

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'event_name' => $validated['event_name'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'has_error' => $validated['has_error'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'page' => (int) ($validated['page'] ?? 1),
            'sort' => $sort,
            'direction' => ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ];

        $campusId = session('current_campus_id');

        return Inertia::render('Admin/Notifications/Ops/Outbox', [
            'outbox' => $this->outboxQuery->handle($filters, $campusId),
            'filters' => $filters,
            'statuses' => $this->getOutboxStatuses(),
            'eventNames' => $this->getDistinctEventNames(),
        ]);
    }

    public function outboxDetail(NotificationEventOutbox $outbox): Response
    {
        $this->authorize('view_any_notification');

        $campusId = session('current_campus_id');
        if ($campusId !== null && $outbox->campus_id !== $campusId) {
            abort(404);
        }

        return Inertia::render('Admin/Notifications/Ops/OutboxDetail', [
            'outbox' => $outbox->load(['messages', 'messages.deliveries', 'messages.recipient:id,name,email']),
        ]);
    }

    public function retryOutbox(NotificationEventOutbox $outbox, RetryOutboxAction $action): JsonResponse
    {
        $this->authorize('view_any_notification');

        $campusId = session('current_campus_id');
        if ($campusId !== null && $outbox->campus_id !== $campusId) {
            abort(404);
        }

        $success = $action->run($outbox);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot retry outbox entry with status: '.$outbox->status->value,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Outbox entry queued for retry.',
        ]);
    }

    public function deliveries(Request $request): Response
    {
        $this->authorize('view_any_notification');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'has_error' => ['nullable', 'string', 'in:yes,no'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? 'created_at', self::ALLOWED_DELIVERY_SORTS, true)
            ? ($validated['sort'] ?? 'created_at')
            : 'created_at';

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'channel' => $validated['channel'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'has_error' => $validated['has_error'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'page' => (int) ($validated['page'] ?? 1),
            'sort' => $sort,
            'direction' => ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ];

        $campusId = session('current_campus_id');

        return Inertia::render('Admin/Notifications/Ops/Deliveries', [
            'deliveries' => $this->deliveriesQuery->handle($filters, $campusId),
            'filters' => $filters,
            'statuses' => $this->getDeliveryStatuses(),
            'channels' => $this->getDeliveryChannels(),
        ]);
    }

    public function retryDelivery(NotificationDelivery $delivery, RetryDeliveryAction $action): JsonResponse
    {
        $this->authorize('view_any_notification');

        $campusId = session('current_campus_id');
        if ($campusId !== null && $delivery->message && $delivery->message->campus_id !== $campusId) {
            abort(404);
        }

        $success = $action->run($delivery);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot retry delivery with status: '.$delivery->status->value,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Delivery queued for retry.',
        ]);
    }

    public function messages(Request $request): Response
    {
        $this->authorize('view_any_notification');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'type_key' => ['nullable', 'string', 'max:100'],
            'read_status' => ['nullable', 'string', 'in:read,unread'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? 'created_at', self::ALLOWED_MESSAGE_SORTS, true)
            ? ($validated['sort'] ?? 'created_at')
            : 'created_at';

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'type_key' => $validated['type_key'] ?? null,
            'read_status' => $validated['read_status'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'page' => (int) ($validated['page'] ?? 1),
            'sort' => $sort,
            'direction' => ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ];

        $campusId = session('current_campus_id');

        return Inertia::render('Admin/Notifications/Ops/Messages', [
            'messages' => $this->messagesQuery->handle($filters, $campusId),
            'filters' => $filters,
            'statuses' => $this->getMessageStatuses(),
            'typeKeys' => $this->getDistinctTypeKeys(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getOutboxStatuses(): array
    {
        return collect(NotificationOutboxStatus::cases())
            ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function getDeliveryStatuses(): array
    {
        return collect(NotificationDeliveryStatus::cases())
            ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function getDeliveryChannels(): array
    {
        return collect(NotificationDeliveryChannel::cases())
            ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function getMessageStatuses(): array
    {
        return collect(NotificationMessageStatus::cases())
            ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    private function getDistinctEventNames(): array
    {
        $query = NotificationEventOutbox::query()->distinct();
        $campusId = session('current_campus_id');
        if ($campusId !== null) {
            $query->where('campus_id', $campusId);
        }

        return $query->pluck('event_name')->toArray();
    }

    /**
     * @return array<int, string>
     */
    private function getDistinctTypeKeys(): array
    {
        $query = \App\Modules\Notification\Models\NotificationMessage::query()->distinct();
        $campusId = session('current_campus_id');
        if ($campusId !== null) {
            $query->where('campus_id', $campusId);
        }

        return $query->pluck('type_key')->toArray();
    }
}
