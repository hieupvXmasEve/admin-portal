<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListDngWebhookEventsQuery
{
    private const SORTABLE = [
        'created_at' => 'created_at',
        'processed_at' => 'processed_at',
        'processing_status' => 'processing_status',
        'event_type' => 'event_type',
    ];

    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'processing_status' => 'nullable|string|max:50',
            'event_type' => 'nullable|string|max:50',
            'checksum_validity' => 'nullable|in:all,valid,invalid',
            'linked_request' => 'nullable|in:all,linked,orphan',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $this->buildFilteredQuery($validated)
            ->with([
                'dngPaymentRequest:id,student_id,campus_code,status,dng_payment_id,payment_id',
                'dngPaymentRequest.payment:id,status,amount,external_ref',
            ]);

        $sort = self::SORTABLE[$validated['sort'] ?? 'created_at'] ?? 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $items = $query
            ->orderBy($sort, $direction)
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        $items->through(function (DngWebhookEvent $event): array {
            $linkedRequest = $event->dngPaymentRequest;
            $student = $linkedRequest === null ? null : $this->studentReferences->find((int) $linkedRequest->student_id);

            return [
                'id' => $event->id,
                'created_at' => $event->created_at?->toDateTimeString(),
                'processed_at' => $event->processed_at?->toDateTimeString(),
                'dng_payment_id' => $event->dng_payment_id,
                'event_type' => $event->event_type,
                'processing_status' => $event->processing_status,
                'is_valid_checksum' => (bool) $event->is_valid_checksum,
                'error_message' => $event->error_message,
                'linked_request' => $linkedRequest ? [
                    'id' => $linkedRequest->id,
                    'status' => $linkedRequest->status,
                    'student' => $student ? [
                        'student_code' => $student->studentCode,
                        'full_name' => $student->fullName,
                    ] : null,
                    'payment' => $linkedRequest->payment ? [
                        'id' => $linkedRequest->payment->id,
                        'status' => $linkedRequest->payment->status,
                    ] : null,
                ] : null,
            ];
        });

        return [
            'items' => $items,
            'stats' => $this->buildStats($validated),
            'filters' => [
                'search' => $validated['search'] ?? '',
                'processing_status' => $validated['processing_status'] ?? '',
                'event_type' => $validated['event_type'] ?? '',
                'checksum_validity' => $validated['checksum_validity'] ?? 'all',
                'linked_request' => $validated['linked_request'] ?? 'all',
                'created_from' => $validated['created_from'] ?? '',
                'created_to' => $validated['created_to'] ?? '',
                'sort' => array_search($sort, self::SORTABLE, true) ?: 'created_at',
                'direction' => $direction,
                'per_page' => (int) ($validated['per_page'] ?? 15),
            ],
        ];
    }

    private function buildFilteredQuery(array $filters): Builder
    {
        $query = DngWebhookEvent::query();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('dng_payment_id', 'like', "%{$search}%")
                    ->orWhere('payload_hash', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('payload->StudentId', 'like', "%{$search}%")
                    ->orWhere('payload->ItemId', 'like', "%{$search}%")
                    ->orWhereHas('dngPaymentRequest', function (Builder $requestQuery) use ($search) {
                        $requestQuery->where('student_code', 'like', "%{$search}%")
                            ->orWhere('item_id', 'like', "%{$search}%");
                    });
            });
        }

        if (filled($filters['processing_status'] ?? null)) {
            $query->where('processing_status', $filters['processing_status']);
        }

        if (filled($filters['event_type'] ?? null)) {
            $query->where('event_type', $filters['event_type']);
        }

        if (($filters['checksum_validity'] ?? 'all') === 'valid') {
            $query->where('is_valid_checksum', true);
        } elseif (($filters['checksum_validity'] ?? 'all') === 'invalid') {
            $query->where('error_category', DngWebhookEvent::ERROR_CATEGORY_CHECKSUM);
        }

        if (($filters['linked_request'] ?? 'all') === 'linked') {
            $query->whereNotNull('dng_payment_request_id');
        } elseif (($filters['linked_request'] ?? 'all') === 'orphan') {
            $query->whereNull('dng_payment_request_id');
        }

        if (filled($filters['created_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (filled($filters['created_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        return $query;
    }

    private function buildStats(array $filters): array
    {
        $query = $this->buildFilteredQuery($filters);

        return [
            'total' => (clone $query)->count(),
            'pending_count' => (clone $query)
                ->whereIn('processing_status', [
                    DngWebhookEvent::STATUS_RECEIVED,
                    DngWebhookEvent::STATUS_PROCESSING,
                    DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                ])
                ->count(),
            'processed_count' => (clone $query)->where('processing_status', DngWebhookEvent::STATUS_PROCESSED)->count(),
            'mismatch_count' => (clone $query)->where('processing_status', DngWebhookEvent::STATUS_MISMATCH)->count(),
            'failed_count' => (clone $query)->where('processing_status', DngWebhookEvent::STATUS_FAILED_TERMINAL)->count(),
            'invalid_checksum_count' => (clone $query)
                ->where('error_category', DngWebhookEvent::ERROR_CATEGORY_CHECKSUM)
                ->count(),
            'orphan_count' => (clone $query)->whereNull('dng_payment_request_id')->count(),
        ];
    }
}
