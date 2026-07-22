<?php

declare(strict_types=1);

namespace App\Modules\Notification\Queries;

use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListMessagesQuery
{
    private const SORTABLE_COLUMNS = [
        'created_at' => 'notification_messages.created_at',
        'type_key' => 'notification_messages.type_key',
        'status' => 'notification_messages.status',
        'read_at' => 'notification_messages.read_at',
    ];

    public function handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $sortKey = (string) ($filters['sort'] ?? 'created_at');
        $sortColumn = self::SORTABLE_COLUMNS[$sortKey] ?? self::SORTABLE_COLUMNS['created_at'];
        $sortDirection = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return NotificationMessage::query()
            ->with(['recipient:id,name,email', 'deliveries:id,message_id,channel,status'])
            ->select('notification_messages.*')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('event_id', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('type_key', 'like', "%{$search}%")
                        ->orWhere('recipient_email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['type_key'] ?? null, fn (Builder $q, string $t) => $q->where('type_key', $t))
            ->when($filters['read_status'] ?? null, function (Builder $q, string $r): void {
                if ($r === 'read') {
                    $q->whereNotNull('read_at');
                } elseif ($r === 'unread') {
                    $q->whereNull('read_at');
                }
            })
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))
            ->when($campusId, fn (Builder $q, int $id) => $q->where('campus_id', $id))
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('notification_messages.id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1)
            )
            ->withQueryString();
    }
}
