<?php

declare(strict_types=1);

namespace App\Modules\Notification\Queries;

use App\Modules\Notification\Models\NotificationEventOutbox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListOutboxQuery
{
    private const SORTABLE_COLUMNS = [
        'occurred_at' => 'notification_event_outbox.occurred_at',
        'event_name' => 'notification_event_outbox.event_name',
        'status' => 'notification_event_outbox.status',
        'attempts' => 'notification_event_outbox.attempts',
        'created_at' => 'notification_event_outbox.created_at',
    ];

    public function handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $sortKey = (string) ($filters['sort'] ?? 'occurred_at');
        $sortColumn = self::SORTABLE_COLUMNS[$sortKey] ?? self::SORTABLE_COLUMNS['occurred_at'];
        $sortDirection = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return NotificationEventOutbox::query()
            ->select('notification_event_outbox.*')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('event_id', 'like', "%{$search}%")
                        ->orWhere('event_name', 'like', "%{$search}%")
                        ->orWhere('aggregate_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['event_name'] ?? null, fn (Builder $q, string $e) => $q->where('event_name', $e))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $d) => $q->whereDate('occurred_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $d) => $q->whereDate('occurred_at', '<=', $d))
            ->when($campusId, fn (Builder $q, int $id) => $q->where('campus_id', $id))
            ->when(
                ($filters['has_error'] ?? null) === 'yes',
                fn (Builder $q) => $q->whereNotNull('last_error')->where('last_error', '!=', '')
            )
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1)
            )
            ->withQueryString();
    }
}
