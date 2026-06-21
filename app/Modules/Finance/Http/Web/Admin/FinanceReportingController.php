<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Http\Requests\Reporting\ListCollectionProgressRequest;
use App\Modules\Finance\Http\Requests\Reporting\ListDngLifecycleRequest;
use App\Modules\Finance\Http\Requests\Reporting\ListFeeMonitorRequest;
use App\Modules\Finance\Queries\Reporting\ListCollectionProgressQuery;
use App\Modules\Finance\Queries\Reporting\ListDngLifecycleQuery;
use App\Modules\Finance\Queries\Reporting\ListFeeMonitorQuery;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use App\Modules\Finance\Support\Reporting\FeeMonitorAcadRetGate;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class FinanceReportingController extends Controller
{
    private const DEFAULT_VIEW = 'collection-progress';

    /** @var list<array{key: string, label: string, description: string, status: string, obeys_semester: bool}> */
    private const VIEWS = [
        [
            'key' => 'fee-monitor',
            'label' => 'Fee Monitor',
            'description' => 'Expected, generated, missing, blocked, and paid fee completeness.',
            'status' => 'implemented',
            'obeys_semester' => true,
        ],
        [
            'key' => 'collection-progress',
            'label' => 'Collection Progress',
            'description' => 'Billed, paid, outstanding, overdue, overpaid, and unapplied balances.',
            'status' => 'implemented',
            'obeys_semester' => true,
        ],
        [
            'key' => 'dng-lifecycle',
            'label' => 'DNG/Payment Lifecycle',
            'description' => 'DNG requests, webhooks, payment bridge, invoice, and allocation attention queue.',
            'status' => 'implemented',
            'obeys_semester' => false,
        ],
    ];

    public function index(
        ListFeeMonitorRequest $request,
        ListFeeMonitorQuery $listQuery,
    ): Response {
        $activeView = $this->activeView((string) $request->query('view', self::DEFAULT_VIEW));
        $computedAt = now()->toIso8601String();

        $payload = [
            'active_view' => $activeView,
            'views' => self::VIEWS,
            'computed_at' => $computedAt,
            'actions' => [
                'export_enabled' => false,
            ],
        ];

        if ($activeView === 'fee-monitor') {
            $payload = array_merge($payload, $this->feeMonitorPayload($request, $listQuery, $computedAt));
        }

        if ($activeView === 'collection-progress') {
            $payload = array_merge($payload, $this->collectionProgressPayload($computedAt));
        }

        if ($activeView === 'dng-lifecycle') {
            $payload = array_merge($payload, $this->dngLifecyclePayload($computedAt));
        }

        return Inertia::render('Finance/Reporting/Index', $payload);
    }

    /**
     * DNG/Payment Lifecycle lens (FIN-REV-019). Campus-bound and intentionally
     * NOT hard-filtered by the global Finance semester — the selected semester is
     * passed only so rows relating entirely to other semesters can be flagged and
     * filtered (`outside_selected_semester`). Read-only: no mutation actions.
     *
     * @return array<string, mixed>
     */
    private function dngLifecyclePayload(string $computedAt): array
    {
        $request = app(ListDngLifecycleRequest::class);
        $listQuery = app(ListDngLifecycleQuery::class);

        $filters = array_replace([
            'attention_bucket' => 'all',
            'dng_status' => 'all',
            'payment_bridge' => 'all',
            'webhook_state' => 'all',
            'invoice_state' => 'all',
            'allocation_state' => 'all',
            'flow_state' => 'all',
            'related_semester' => 'all',
            'outside_selected_semester' => 'all',
            'fee_type' => 'all',
            'amount_min' => '',
            'amount_max' => '',
            'created_from' => '',
            'created_to' => '',
            'paid_from' => '',
            'paid_to' => '',
            'search' => '',
            'per_page' => 20,
            'page' => 1,
        ], $request->validated());

        $selectedSemesterId = $this->resolveSemesterId();
        $result = $listQuery->handle($selectedSemesterId, $filters);

        return [
            'dng_lifecycle' => [
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'breakdowns' => $result['breakdowns'],
                'filters' => $filters,
                'filter_options' => $listQuery->filterOptions($selectedSemesterId),
                'meta' => array_merge([
                    'selected_semester_id' => $selectedSemesterId,
                    'campus_bound' => true,
                    'obeys_semester' => false,
                ], $result['meta']),
                'computed_at' => $computedAt,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionProgressPayload(string $computedAt): array
    {
        $request = app(ListCollectionProgressRequest::class);
        $listQuery = app(ListCollectionProgressQuery::class);

        $filters = array_replace([
            'program_id' => 'all',
            'intake_semester_id' => 'all',
            'cohort' => 'all',
            'fee_type' => 'all',
            'balance_state' => 'all',
            'aging_bucket' => 'all',
            'student_status' => 'all',
            'search' => '',
            'per_page' => 20,
            'page' => 1,
        ], $request->validated());

        $semesterId = $this->resolveSemesterId();

        $result = $semesterId
            ? $listQuery->handle($semesterId, $filters)
            : [
                'rows' => new LengthAwarePaginator([], 0, 20, 1, ['path' => request()->url(), 'query' => request()->query()]),
                'summary' => $this->emptyCollectionSummary(),
                'breakdowns' => $this->emptyCollectionBreakdowns(),
            ];

        return [
            'collection_progress' => [
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'breakdowns' => $result['breakdowns'],
                'filters' => $filters,
                'filter_options' => $semesterId ? $listQuery->filterOptions($semesterId) : $this->emptyCollectionFilterOptions(),
                'meta' => [
                    'semester_id' => $semesterId,
                ],
                'computed_at' => $computedAt,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCollectionSummary(): array
    {
        return [
            'student_count' => 0,
            'billed_total' => 0.0,
            'paid_total' => 0.0,
            'outstanding_total' => 0.0,
            'overdue_total' => 0.0,
            'overpaid_total' => 0.0,
            'unapplied_total' => 0.0,
            'collection_rate' => null,
            'unpaid_count' => 0,
            'partially_paid_count' => 0,
            'paid_count' => 0,
            'overdue_count' => 0,
            'overpaid_count' => 0,
            'unapplied_count' => 0,
            'lifecycle_exception_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCollectionBreakdowns(): array
    {
        return [
            'by_fee_type' => [],
            'by_program' => [],
            'by_intake' => [],
            'by_cohort' => [],
            'by_balance_state' => [],
            'by_aging_bucket' => [],
            'by_lifecycle_exception' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCollectionFilterOptions(): array
    {
        return [
            'programs' => [],
            'intakes' => [],
            'cohorts' => [],
            'fee_types' => [],
            'balance_states' => [],
            'aging_buckets' => [],
            'student_statuses' => [],
            'semester_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feeMonitorPayload(
        ListFeeMonitorRequest $request,
        ListFeeMonitorQuery $listQuery,
        string $computedAt,
    ): array {
        $validated = array_replace([
            'program_id' => 'all',
            'intake_semester_id' => 'all',
            'cohort' => 'all',
            'expected_fee_type' => 'all',
            'generation_state' => 'all',
            'payment_state' => 'all',
            'student_status' => 'all',
            'search' => '',
            'per_page' => 20,
            'page' => 1,
        ], $request->validated());

        $semesterId = $this->resolveSemesterId();
        $filters = $validated;

        $result = $semesterId
            ? $listQuery->handle($semesterId, $filters)
            : [
                'rows' => new LengthAwarePaginator([], 0, 20, 1, ['path' => request()->url(), 'query' => request()->query()]),
                'summary' => [
                    'missing_count' => 0,
                    'generated_count' => 0,
                    'skipped_count' => 0,
                    'voided_count' => 0,
                    'blocked_count' => 0,
                    'paid_count' => 0,
                    'partially_paid_count' => 0,
                    'outstanding_count' => 0,
                    'total_count' => 0,
                ],
            ];

        return [
            'fee_monitor' => [
                'rows' => $result['rows'],
                'summary' => $semesterId ? $result['summary'] : [
                    'missing_count' => 0,
                    'generated_count' => 0,
                    'skipped_count' => 0,
                    'voided_count' => 0,
                    'blocked_count' => 0,
                    'paid_count' => 0,
                    'partially_paid_count' => 0,
                    'outstanding_count' => 0,
                    'total_count' => 0,
                ],
                'filters' => $filters,
                'filter_options' => $semesterId ? $listQuery->filterOptions($semesterId) : [
                    'programs' => [],
                    'intakes' => [],
                    'cohorts' => [],
                    'expected_fee_types' => [],
                    'generation_states' => [],
                    'student_statuses' => [],
                    'semester_id' => null,
                ],
                'meta' => [
                    'semester_id' => $semesterId,
                    'acad_ret_gate' => [
                        'missing_inference_enabled' => FeeMonitorAcadRetGate::missingInferenceEnabled(),
                        'excluded_missing_sources' => FeeMonitorAcadRetGate::excludedMissingSources(),
                    ],
                ],
                'computed_at' => $computedAt,
                'permissions' => [
                    'can_batch_handoff' => $request->user()?->can('create_finance_charges') ?? false,
                ],
            ],
        ];
    }

    private function resolveSemesterId(): ?int
    {
        return FinanceSemesterContextResolver::selectedId();
    }

    private function activeView(string $candidate): string
    {
        $keys = array_column(self::VIEWS, 'key');

        return in_array($candidate, $keys, true) ? $candidate : self::DEFAULT_VIEW;
    }
}
