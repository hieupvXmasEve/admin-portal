<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Support\Reporting\DngLifecycleCatalog as Catalog;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Read-only DNG/Payment Lifecycle lens (FIN-REV-019).
 *
 * Row grain is `DNG/payment request`, scoped to the authenticated account campus
 * via the linked student. This view is deliberately NOT hard-filtered by the
 * global Finance semester: a stuck request must stay visible no matter which
 * semester is selected. Related semester is exposed as evidence (and as an
 * optional filter) only — it is never an implicit filter.
 */
class ListDngLifecycleQuery
{
    /**
     * Upper bound on how many campus DNG requests are pulled into memory for
     * classification per request. The lens is a triage queue, so when the
     * campus-and-filter scope exceeds this, the most recent rows are scanned and
     * the truncation is surfaced (and logged) rather than silently dropped.
     */
    private const MAX_SCAN = 2000;

    public function __construct(
        private readonly StudentReferenceReader $studentReferences,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: LengthAwarePaginator, summary: array<string, mixed>, breakdowns: array<string, mixed>, meta: array<string, mixed>}
     */
    public function handle(?int $selectedSemesterId, array $filters = []): array
    {
        $matched = $this->matchedCount($filters);
        $truncated = $matched > self::MAX_SCAN;

        if ($truncated) {
            Log::warning('DNG lifecycle reporting scan truncated', [
                'matched' => $matched,
                'scan_cap' => self::MAX_SCAN,
                'campus_id' => $this->campusId(),
            ]);
        }

        $rows = $this->collectRows($selectedSemesterId, $filters);

        $perPage = in_array((int) ($filters['per_page'] ?? 20), [20, 50, 100], true)
            ? (int) $filters['per_page']
            : 20;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageRows = $rows->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageRows,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );

        $summaryQuery = app(GetDngLifecycleSummaryQuery::class);

        return [
            'rows' => $paginator,
            'summary' => $summaryQuery->fromRows($rows),
            'breakdowns' => $summaryQuery->breakdownsFromRows($rows),
            'meta' => [
                'total_matched' => $matched,
                'scan_cap' => self::MAX_SCAN,
                'truncated' => $truncated,
            ],
        ];
    }

    /**
     * Count of campus-scoped requests matching the DB-expressible filters, before
     * the in-memory scan cap and the computed-state filters are applied. Used to
     * detect (and surface) when the triage scan is truncated.
     *
     * @param  array<string, mixed>  $filters
     */
    public function matchedCount(array $filters = []): int
    {
        $campusId = $this->campusId();

        return $campusId === null ? 0 : $this->baseQuery($campusId, $filters)->count();
    }

    /**
     * Build the fully-filtered row collection used by both pagination and the
     * summary/breakdown statistics so the numbers always match the visible scope.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function collectRows(?int $selectedSemesterId, array $filters = []): Collection
    {
        $campusId = $this->campusId();
        if ($campusId === null) {
            return collect();
        }

        $now = now();

        $requests = $this->baseQuery($campusId, $filters)
            ->with([
                'chargeLinks:id,dng_payment_request_id,finance_charge_id',
                'chargeLinks.financeCharge:id,semester_id',
                'webhookEvents:id,dng_payment_request_id,is_valid_checksum,processing_status,next_retry_at',
            ])
            ->orderByDesc('created_at')
            ->limit(self::MAX_SCAN)
            ->get();
        $students = $this->studentReferences->findMany(
            $requests->pluck('student_id')->map(static fn (int|string $id): int => (int) $id)->unique()->values()->all(),
        );
        $periodIds = $requests
            ->pluck('semester_id')
            ->merge($requests->flatMap(fn (DngPaymentRequest $request) => $request->chargeLinks->pluck('financeCharge.semester_id')))
            ->filter()
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $periods = $this->academicPeriods->findMany($periodIds);

        $rows = $requests
            ->map(fn (DngPaymentRequest $request): ?array => $this->buildRow(
                $request,
                $students[(int) $request->student_id] ?? null,
                $periods,
                $selectedSemesterId,
                $now,
            ))
            ->filter()
            ->values();

        return $this->sortRows($this->applyComputedFilters($rows, $filters));
    }

    /**
     * DB-level filtering: campus scope plus everything that maps cleanly onto a
     * column (status, fee type, amount range, created/paid date ranges, search).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<DngPaymentRequest>
     */
    private function baseQuery(int $campusId, array $filters): Builder
    {
        $query = DngPaymentRequest::query()
            ->whereIn('student_id', $this->studentReferences->idsForCampus($campusId))
            ->when(
                ! empty($filters['dng_status']) && $filters['dng_status'] !== 'all',
                fn (Builder $query) => $query->where('status', (string) $filters['dng_status']),
            )
            ->when(
                ! empty($filters['fee_type']) && $filters['fee_type'] !== 'all',
                fn (Builder $query) => $query->where('fee_type', (string) $filters['fee_type']),
            )
            ->when(
                isset($filters['amount_min']) && $filters['amount_min'] !== '' && $filters['amount_min'] !== null,
                fn (Builder $query) => $query->where('amount', '>=', (float) $filters['amount_min']),
            )
            ->when(
                isset($filters['amount_max']) && $filters['amount_max'] !== '' && $filters['amount_max'] !== null,
                fn (Builder $query) => $query->where('amount', '<=', (float) $filters['amount_max']),
            )
            ->when(
                ! empty($filters['created_from']),
                fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse((string) $filters['created_from'])->startOfDay()),
            )
            ->when(
                ! empty($filters['created_to']),
                fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse((string) $filters['created_to'])->endOfDay()),
            )
            ->when(
                ! empty($filters['paid_from']),
                fn (Builder $query) => $query->where('paid_at', '>=', Carbon::parse((string) $filters['paid_from'])->startOfDay()),
            )
            ->when(
                ! empty($filters['paid_to']),
                fn (Builder $query) => $query->where('paid_at', '<=', Carbon::parse((string) $filters['paid_to'])->endOfDay()),
            )
            ->when(
                ! empty($filters['related_semester']) && $filters['related_semester'] !== 'all',
                fn (Builder $query) => $this->applyRelatedSemesterDbHint($query, (int) $filters['related_semester']),
            )
            ->when(
                ! empty($filters['search']),
                fn (Builder $query) => $this->applySearch($query, (string) $filters['search'], $campusId),
            );

        return $this->applyDerivedStateHints($query, $filters);
    }

    /**
     * Pre-narrow the in-memory scan using the computed-state filters that derive
     * purely from columns on `dng_payment_requests` (no webhook events, no clock).
     * These are EXACT mirrors of the catalog classifiers — the authoritative cut
     * still happens in {@see applyComputedFilters}; this only shrinks the load.
     * Time-sensitive (pending_stale, overdue_pushed) and event-sensitive
     * (webhook_state, webhook_problem, flow retrying) filters are intentionally
     * left to the PHP pass so a clock/skew can never exclude a valid row here.
     *
     * @param  Builder<DngPaymentRequest>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<DngPaymentRequest>
     */
    private function applyDerivedStateHints(Builder $query, array $filters): Builder
    {
        $cancelStatuses = [DngPaymentRequest::STATUS_CANCELLED, DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG];
        $invoicedStatuses = [DngPaymentRequest::STATUS_PAID_INVOICED, DngPaymentRequest::STATUS_RECONCILED];
        $allocationPending = [DngPaymentRequest::STATUS_PAID_UNINVOICED, DngPaymentRequest::STATUS_PAID_INVOICED];

        return $query
            ->when(($filters['payment_bridge'] ?? 'all') === Catalog::BRIDGE_BRIDGED, fn (Builder $q) => $q->whereNotNull('payment_id'))
            ->when(($filters['payment_bridge'] ?? 'all') === Catalog::BRIDGE_NOT_BRIDGED, fn (Builder $q) => $q->whereNull('payment_id'))
            ->when(($filters['invoice_state'] ?? 'all') === Catalog::INVOICE_INVOICED, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereNotNull('invoice_serial_number')->orWhereIn('status', $invoicedStatuses)))
            ->when(($filters['invoice_state'] ?? 'all') === Catalog::INVOICE_UNINVOICED, fn (Builder $q) => $q
                ->whereNull('invoice_serial_number')->where('status', DngPaymentRequest::STATUS_PAID_UNINVOICED))
            ->when(($filters['invoice_state'] ?? 'all') === Catalog::INVOICE_NOT_APPLICABLE, fn (Builder $q) => $q
                ->whereNull('invoice_serial_number')
                ->whereNotIn('status', [DngPaymentRequest::STATUS_PAID_UNINVOICED, ...$invoicedStatuses]))
            ->when(($filters['allocation_state'] ?? 'all') === Catalog::ALLOCATION_RECONCILED, fn (Builder $q) => $q->where('status', DngPaymentRequest::STATUS_RECONCILED))
            ->when(($filters['allocation_state'] ?? 'all') === Catalog::ALLOCATION_PENDING, fn (Builder $q) => $q->whereIn('status', $allocationPending))
            ->when(($filters['allocation_state'] ?? 'all') === Catalog::ALLOCATION_NOT_APPLICABLE, fn (Builder $q) => $q
                ->whereNotIn('status', [DngPaymentRequest::STATUS_RECONCILED, ...$allocationPending]))
            ->when(($filters['flow_state'] ?? 'all') === Catalog::FLOW_CANCELLED, fn (Builder $q) => $q->whereIn('status', $cancelStatuses))
            ->when(($filters['flow_state'] ?? 'all') === Catalog::FLOW_ERROR, fn (Builder $q) => $q
                ->whereNotIn('status', $cancelStatuses)
                ->where(fn (Builder $w) => $w->where('status', DngPaymentRequest::STATUS_FAILED)->orWhereNotNull('error_message')))
            ->when(($filters['attention_bucket'] ?? 'all') === Catalog::BUCKET_FAILED_REQUEST, fn (Builder $q) => $q->where('status', DngPaymentRequest::STATUS_FAILED))
            ->when(($filters['attention_bucket'] ?? 'all') === Catalog::BUCKET_PAID_UNINVOICED, fn (Builder $q) => $q->where('status', DngPaymentRequest::STATUS_PAID_UNINVOICED));
    }

    /**
     * The related-semester filter is computed over the full lineage in PHP, but
     * we can safely pre-narrow at the DB layer to requests that touch the chosen
     * semester through any of their two lineage sources. The PHP pass then
     * applies the exact membership test.
     *
     * @param  Builder<DngPaymentRequest>  $query
     * @return Builder<DngPaymentRequest>
     */
    private function applyRelatedSemesterDbHint(Builder $query, int $semesterId): Builder
    {
        return $query->where(function (Builder $inner) use ($semesterId): void {
            $inner->where('semester_id', $semesterId)
                ->orWhereHas('chargeLinks.financeCharge', fn (Builder $charge) => $charge->where('semester_id', $semesterId));
        });
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     * @return Builder<DngPaymentRequest>
     */
    private function applySearch(Builder $query, string $search, int $campusId): Builder
    {
        $studentIds = $this->studentReferences->idsMatchingSearch($search, $campusId);

        return $query->where(function (Builder $inner) use ($search, $studentIds): void {
            $inner->where('item_id', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%")
                ->orWhere('dng_payment_id', 'like', "%{$search}%")
                ->orWhere('dng_transaction_id', 'like', "%{$search}%")
                ->orWhere('invoice_serial_number', 'like', "%{$search}%")
                ->orWhereIn('student_id', $studentIds);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(
        DngPaymentRequest $request,
        ?StudentReference $student,
        array $periods,
        ?int $selectedSemesterId,
        Carbon $now,
    ): ?array {
        if ($student === null) {
            return null;
        }

        $webhookEvents = $this->webhookEventArray($request->webhookEvents);
        $webhookState = Catalog::deriveWebhookState($webhookEvents);

        $relatedSemesters = $this->relatedSemesters($request, $periods);
        $relatedIds = array_map(static fn (array $semester) => $semester['id'], $relatedSemesters);
        $semesterState = Catalog::relatedSemesterState($relatedIds);
        $outsideSelected = $selectedSemesterId !== null
            && $relatedIds !== []
            && ! in_array($selectedSemesterId, $relatedIds, true);

        $status = (string) $request->status;
        $invoiceState = Catalog::deriveInvoiceState($status, $request->invoice_serial_number);
        $allocationState = Catalog::deriveAllocationState($status);
        $paymentBridge = Catalog::derivePaymentBridge($request->payment_id);
        $flowState = Catalog::deriveFlowState($status, filled($request->error_message), $this->isRetrying($request->webhookEvents, $now));

        $buckets = Catalog::attentionBucketsFor($status, $request->created_at, $request->due_date, $webhookState, $now);

        return [
            'row_key' => 'dng-'.$request->id,
            'request_id' => $request->id,
            'created_at' => $request->created_at?->toIso8601String(),
            'due_date' => $request->due_date?->toDateString(),
            'paid_at' => $request->paid_at?->toIso8601String(),
            'student' => [
                'id' => $student->id,
                'student_code' => $student->studentCode,
                'full_name' => $student->fullName,
                'status' => $student->status,
                'status_label' => $student->statusLabel,
            ],
            'fee_type' => $request->fee_type,
            'amount' => round((float) $request->amount, 2),
            'status' => $status,
            'status_label' => Catalog::statusLabel($status),
            'payment_bridge' => $paymentBridge,
            'payment_bridge_label' => Catalog::paymentBridgeLabel($paymentBridge),
            'payment_id' => $request->payment_id,
            'webhook_state' => $webhookState,
            'webhook_state_label' => Catalog::webhookStateLabel($webhookState),
            'webhook_event_count' => count($webhookEvents),
            'invoice_state' => $invoiceState,
            'invoice_state_label' => Catalog::invoiceStateLabel($invoiceState),
            'invoice_serial_number' => $request->invoice_serial_number,
            'allocation_state' => $allocationState,
            'allocation_state_label' => Catalog::allocationStateLabel($allocationState),
            'flow_state' => $flowState,
            'flow_state_label' => Catalog::flowStateLabel($flowState),
            'related_semester_state' => $semesterState,
            'related_semester_state_label' => Catalog::semesterStateLabel($semesterState),
            'related_semesters' => $relatedSemesters,
            'outside_selected_semester' => $outsideSelected,
            'attention_buckets' => $buckets,
            'attention_reasons' => array_map([Catalog::class, 'attentionBucketLabel'], $buckets),
            'needs_attention' => $buckets !== [],
            'error_message' => filled($request->error_message) ? (string) $request->error_message : null,
            'drilldowns' => [
                'dng_request_id' => $request->id,
                'student_id' => $student->id,
                'has_webhooks' => $webhookEvents !== [],
                'webhook_event_id' => $this->webhookFocusId($request->webhookEvents),
            ],
        ];
    }

    /**
     * The webhook event a triage operator should open first: the problem event
     * (invalid checksum, mismatch, or failed) if any, otherwise the most recent
     * event by id. Null when the request has no webhook events.
     *
     * @param  EloquentCollection<int, DngWebhookEvent>  $events
     */
    private function webhookFocusId(EloquentCollection $events): ?int
    {
        if ($events->isEmpty()) {
            return null;
        }

        $problem = $events->first(fn (DngWebhookEvent $event) => $event->is_valid_checksum === false
            || in_array($event->processing_status, [
                DngWebhookEvent::STATUS_MISMATCH,
                DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                DngWebhookEvent::STATUS_FAILED_TERMINAL,
            ], true));

        return (int) ($problem?->id ?? $events->sortByDesc('id')->first()->id);
    }

    /**
     * @param  EloquentCollection<int, DngWebhookEvent>  $events
     * @return list<array{is_valid_checksum: bool, processing_status: string}>
     */
    private function webhookEventArray(EloquentCollection $events): array
    {
        return $events
            ->map(fn (DngWebhookEvent $event) => [
                'is_valid_checksum' => (bool) $event->is_valid_checksum,
                'processing_status' => (string) $event->processing_status,
            ])
            ->all();
    }

    /**
     * @param  EloquentCollection<int, DngWebhookEvent>  $events
     */
    private function isRetrying(EloquentCollection $events, Carbon $now): bool
    {
        return $events->contains(fn (DngWebhookEvent $event) => $event->processing_status === DngWebhookEvent::STATUS_FAILED_RETRYABLE
            && $event->next_retry_at !== null
            && $event->next_retry_at->greaterThan($now));
    }

    /**
     * Resolve the semester lineage for a request: its own `semester_id`, the
     * directly linked charge's semester, and every pivot-linked charge semester.
     *
     * @return list<array{id: int, name: string}>
     */
    private function relatedSemesters(DngPaymentRequest $request, array $periods): array
    {
        $semesters = [];

        $add = static function (?int $periodId) use (&$semesters, $periods): void {
            $period = $periodId === null ? null : ($periods[$periodId] ?? null);
            if ($period instanceof AcademicPeriodReference) {
                $semesters[$period->id] = $period->name;
            }
        };

        $add($request->semester_id === null ? null : (int) $request->semester_id);

        foreach ($request->chargeLinks as $link) {
            $semesterId = $link->financeCharge?->semester_id;
            $add($semesterId === null ? null : (int) $semesterId);
        }

        $result = [];
        foreach ($semesters as $id => $name) {
            $result[] = ['id' => (int) $id, 'name' => (string) $name];
        }

        return $result;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyComputedFilters(Collection $rows, array $filters): Collection
    {
        $matches = static fn (string $key, string $rowField): callable => static function (Collection $collection) use ($key, $rowField, $filters) {
            $value = (string) ($filters[$key] ?? 'all');

            return ($value === 'all' || $value === '')
                ? $collection
                : $collection->where($rowField, $value)->values();
        };

        return $rows
            ->pipe($matches('payment_bridge', 'payment_bridge'))
            ->pipe($matches('webhook_state', 'webhook_state'))
            ->pipe($matches('invoice_state', 'invoice_state'))
            ->pipe($matches('allocation_state', 'allocation_state'))
            ->pipe($matches('flow_state', 'flow_state'))
            ->when(
                ! empty($filters['attention_bucket']) && $filters['attention_bucket'] !== 'all',
                fn (Collection $collection) => $collection
                    ->filter(fn (array $row) => in_array((string) $filters['attention_bucket'], $row['attention_buckets'], true))
                    ->values(),
            )
            ->when(
                ! empty($filters['related_semester']) && $filters['related_semester'] !== 'all',
                fn (Collection $collection) => $collection
                    ->filter(fn (array $row) => in_array((int) $filters['related_semester'], array_column($row['related_semesters'], 'id'), true))
                    ->values(),
            )
            ->when(
                ! empty($filters['outside_selected_semester']) && $filters['outside_selected_semester'] !== 'all',
                fn (Collection $collection) => $collection
                    ->filter(fn (array $row) => $row['outside_selected_semester'] === ($filters['outside_selected_semester'] === 'outside'))
                    ->values(),
            )
            ->values();
    }

    /**
     * Attention-first, then most recent: stuck requests bubble to the top of the
     * queue, ties broken by newest creation time.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows): Collection
    {
        return $rows
            ->sort(function (array $a, array $b): int {
                $attention = ($b['needs_attention'] ? 1 : 0) <=> ($a['needs_attention'] ? 1 : 0);

                return $attention !== 0
                    ? $attention
                    : (string) $b['created_at'] <=> (string) $a['created_at'];
            })
            ->values();
    }

    /**
     * Filter dropdown options scoped to the current campus.
     *
     * @return array<string, mixed>
     */
    public function filterOptions(?int $selectedSemesterId): array
    {
        $campusId = $this->campusId();
        if ($campusId === null) {
            return $this->emptyFilterOptions($selectedSemesterId);
        }

        $feeTypes = DngPaymentRequest::query()
            ->whereIn('student_id', $this->studentReferences->idsForCampus($campusId))
            ->distinct()
            ->pluck('fee_type')
            ->filter()
            ->values()
            ->map(fn (string $type) => ['value' => $type, 'label' => $type])
            ->all();

        return [
            'attention_buckets' => $this->options(Catalog::attentionBuckets()),
            'dng_statuses' => $this->options(Catalog::statuses()),
            'payment_bridges' => $this->options(Catalog::paymentBridgeStates()),
            'webhook_states' => $this->options(Catalog::webhookStates()),
            'invoice_states' => $this->options(Catalog::invoiceStates()),
            'allocation_states' => $this->options(Catalog::allocationStates()),
            'flow_states' => $this->options(Catalog::flowStates()),
            'fee_types' => $feeTypes,
            'related_semesters' => $this->relatedSemesterOptions($campusId),
            'selected_semester_id' => $selectedSemesterId,
        ];
    }

    /**
     * Distinct semesters reachable through the campus's DNG requests, across
     * request and allocation lineage sources, ordered newest-first.
     *
     * @return list<array{value: int, label: string}>
     */
    private function relatedSemesterOptions(int $campusId): array
    {
        $base = DngPaymentRequest::query()
            ->whereIn('student_id', $this->studentReferences->idsForCampus($campusId));

        $direct = (clone $base)->whereNotNull('semester_id')->distinct()->pluck('semester_id');
        $linked = DngPaymentRequestCharge::query()
            ->whereHas('dngPaymentRequest', fn (Builder $request) => $request->whereIn(
                'student_id',
                $this->studentReferences->idsForCampus($campusId),
            ))
            ->with('financeCharge:id,semester_id')
            ->get(['id', 'finance_charge_id'])
            ->map(fn ($link) => $link->financeCharge?->semester_id)
            ->filter();

        $ids = collect($direct)->merge($linked)->map(fn ($id) => (int) $id)->unique()->values();

        return collect($this->academicPeriods->findMany($ids->all()))
            ->sortByDesc(fn (AcademicPeriodReference $period) => $period->start_date)
            ->map(fn (AcademicPeriodReference $period): array => ['value' => $period->id, 'label' => $period->name])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{value: string, label: string}>
     */
    private function options(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFilterOptions(?int $selectedSemesterId): array
    {
        return [
            'attention_buckets' => $this->options(Catalog::attentionBuckets()),
            'dng_statuses' => $this->options(Catalog::statuses()),
            'payment_bridges' => $this->options(Catalog::paymentBridgeStates()),
            'webhook_states' => $this->options(Catalog::webhookStates()),
            'invoice_states' => $this->options(Catalog::invoiceStates()),
            'allocation_states' => $this->options(Catalog::allocationStates()),
            'flow_states' => $this->options(Catalog::flowStates()),
            'fee_types' => [],
            'related_semesters' => [],
            'selected_semester_id' => $selectedSemesterId,
        ];
    }

    private function campusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }

        $campus = app('campus');

        return isset($campus->id) ? (int) $campus->id : null;
    }
}
