<?php

declare(strict_types=1);

namespace App\Modules\Notification\Queries;

use App\Modules\Notification\Models\NotificationDelivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveriesQuery
{
    private const SORTABLE_COLUMNS = [
        'created_at' => 'notification_deliveries.created_at',
        'channel' => 'notification_deliveries.channel',
        'status' => 'notification_deliveries.status',
        'attempts' => 'notification_deliveries.attempts',
        'queued_at' => 'notification_deliveries.queued_at',
        'sent_at' => 'notification_deliveries.sent_at',
    ];

    public function handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $sortKey = (string) ($filters['sort'] ?? 'created_at');
        $sortColumn = self::SORTABLE_COLUMNS[$sortKey] ?? self::SORTABLE_COLUMNS['created_at'];
        $sortDirection = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return NotificationDelivery::query()
            ->with(['message:id,event_id,type_key,title,recipient_user_id,recipient_email,campus_id', 'message.recipient:id,name,email'])
            ->select('notification_deliveries.*')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->whereHas('message', function (Builder $q) use ($search): void {
                    $q->where('event_id', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['channel'] ?? null, fn (Builder $q, string $c) => $q->where('channel', $c))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))
            ->when($campusId, fn (Builder $q, int $id) => $q->whereHas('message', fn ($sub) => $sub->where('campus_id', $id)))
            ->when(
                ($filters['has_error'] ?? null) === 'yes',
                fn (Builder $q) => $q->whereNotNull('last_error')->where('last_error', '!=', '')
            )
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('notification_deliveries.id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1)
            )
            ->withQueryString();
    }
}
