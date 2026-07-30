<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListDngPaymentRequestsQuery
{
    private const SORTABLE = [
        'created_at' => 'created_at',
        'student_code' => 'student_code',
        'amount' => 'amount',
        'status' => 'status',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'has_payment' => 'nullable|in:all,yes,no',
            'has_webhook' => 'nullable|in:all,yes,no',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $this->buildFilteredQuery($validated)
            ->with([
                'semester:id,name,code',
                'payment:id,external_ref,status,amount,paid_at',
                'webhookEvents' => fn ($builder) => $builder
                    ->select([
                        'id',
                        'dng_payment_request_id',
                        'dng_payment_id',
                        'processing_status',
                        'event_type',
                        'created_at',
                        'is_valid_checksum',
                    ])
                    ->orderByDesc('created_at'),
            ])
            ->withCount('webhookEvents');

        $sort = self::SORTABLE[$validated['sort'] ?? 'created_at'] ?? 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $items = $query
            ->orderBy($sort, $direction)
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();
        $studentReferences = $this->studentReferences->findMany(
            $items->getCollection()
                ->pluck('student_id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
        );

        $items->through(function (DngPaymentRequest $paymentRequest) use ($studentReferences): array {
            $latestEvent = $paymentRequest->webhookEvents->first();
            $student = $studentReferences[(int) $paymentRequest->student_id] ?? null;

            return [
                'id' => $paymentRequest->id,
                'created_at' => $paymentRequest->created_at?->toDateTimeString(),
                'student' => $student ? [
                    'id' => $student->id,
                    'student_code' => $student->studentCode,
                    'full_name' => $student->fullName,
                ] : null,
                'campus_code' => $paymentRequest->campus_code,
                'student_code' => $paymentRequest->student_code,
                'fee_type' => $paymentRequest->fee_type,
                'description' => $paymentRequest->description,
                'semester' => $paymentRequest->semester ? [
                    'id' => $paymentRequest->semester->id,
                    'name' => $paymentRequest->semester->name,
                    'code' => $paymentRequest->semester->code,
                ] : null,
                'due_date' => $paymentRequest->due_date?->toDateString(),
                'item_id' => $paymentRequest->item_id,
                'amount' => (float) $paymentRequest->amount,
                'status' => $paymentRequest->status,
                'dng_payment_id' => $paymentRequest->dng_payment_id,
                'dng_transaction_id' => $paymentRequest->dng_transaction_id,
                'payment' => $paymentRequest->payment ? [
                    'id' => $paymentRequest->payment->id,
                    'status' => $paymentRequest->payment->status,
                    'amount' => (float) $paymentRequest->payment->amount,
                    'paid_at' => $paymentRequest->payment->paid_at?->toDateTimeString(),
                ] : null,
                'webhook_events_count' => $paymentRequest->webhook_events_count,
                'latest_webhook' => $latestEvent ? [
                    'id' => $latestEvent->id,
                    'dng_payment_id' => $latestEvent->dng_payment_id,
                    'processing_status' => $latestEvent->processing_status,
                    'event_type' => $latestEvent->event_type,
                    'is_valid_checksum' => (bool) $latestEvent->is_valid_checksum,
                    'created_at' => $latestEvent->created_at?->toDateTimeString(),
                ] : null,
            ];
        });

        return [
            'items' => $items,
            'stats' => $this->buildStats($validated),
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'has_payment' => $validated['has_payment'] ?? 'all',
                'has_webhook' => $validated['has_webhook'] ?? 'all',
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
        $query = DngPaymentRequest::query();

        $campus = app()->bound('campus') ? app('campus') : null;
        if ($campus instanceof Campus && $campus->id !== null) {
            $campusId = (int) $campus->id;
            $query->whereIn('student_id', $this->studentReferences->idsForCampus($campusId));
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $campusId = $campus instanceof Campus && $campus->id !== null ? (int) $campus->id : null;
            $matchingStudentIds = $this->studentReferences->idsMatchingSearch($search, $campusId);
            $query->where(function (Builder $builder) use ($matchingStudentIds, $search) {
                $builder->where('student_code', 'like', "%{$search}%")
                    ->orWhere('item_id', 'like', "%{$search}%")
                    ->orWhere('dng_payment_id', 'like', "%{$search}%")
                    ->orWhere('dng_transaction_id', 'like', "%{$search}%")
                    ->orWhereIn('student_id', $matchingStudentIds);
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (($filters['has_payment'] ?? 'all') === 'yes') {
            $query->whereNotNull('payment_id');
        } elseif (($filters['has_payment'] ?? 'all') === 'no') {
            $query->whereNull('payment_id');
        }

        if (($filters['has_webhook'] ?? 'all') === 'yes') {
            $query->whereHas('webhookEvents');
        } elseif (($filters['has_webhook'] ?? 'all') === 'no') {
            $query->whereDoesntHave('webhookEvents');
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
            'pending_count' => (clone $query)->where('status', DngPaymentRequest::STATUS_PENDING)->count(),
            'pushed_count' => (clone $query)->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)->count(),
            'paid_uninvoiced_count' => (clone $query)->where('status', DngPaymentRequest::STATUS_PAID_UNINVOICED)->count(),
            'paid_invoiced_count' => (clone $query)->where('status', DngPaymentRequest::STATUS_PAID_INVOICED)->count(),
            'failed_count' => (clone $query)->where('status', DngPaymentRequest::STATUS_FAILED)->count(),
            'cancelled_count' => (clone $query)->where('status', DngPaymentRequest::STATUS_CANCELLED)->count(),
            'bridged_count' => (clone $query)->whereNotNull('payment_id')->count(),
        ];
    }
}
