<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Http\Requests\Reporting\ListFeeMonitorRequest;
use App\Modules\Finance\Queries\Reporting\ListFeeMonitorQuery;
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
            'status' => 'planned',
            'obeys_semester' => true,
        ],
        [
            'key' => 'dng-lifecycle',
            'label' => 'DNG/Payment Lifecycle',
            'description' => 'DNG requests, webhooks, payment bridge, invoice, and allocation attention queue.',
            'status' => 'planned',
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

        return Inertia::render('Finance/Reporting/Index', $payload);
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
        $selectedId = session('current_semester_id');

        if ($selectedId) {
            return (int) $selectedId;
        }

        return Semester::query()->where('is_active', true)->value('id');
    }

    private function activeView(string $candidate): string
    {
        $keys = array_column(self::VIEWS, 'key');

        return in_array($candidate, $keys, true) ? $candidate : self::DEFAULT_VIEW;
    }
}
