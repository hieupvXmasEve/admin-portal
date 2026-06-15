<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Http\Requests\Audit\FinanceAuditSearchRequest;
use App\Modules\Finance\Queries\Audit\GetFinanceAuditGraphQuery;
use App\Modules\Finance\Queries\Audit\ResolveFinanceAuditSearchQuery;
use App\Modules\Finance\Support\Audit\FinanceAuditWarningBuilder;
use App\Modules\Finance\Support\Audit\FinanceLedgerTimelineBuilder;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;
use App\Modules\Finance\Support\Integrity\FinanceInvariantSampleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only Finance Audit Workspace. Resolves any finance identifier to a
 * student-scoped money graph + signed ledger timeline + integrity warnings.
 *
 * Heavy props (graph/timeline/warnings) are deferred; each deferred closure
 * re-checks campus visibility so a shared/bookmarked link reruns access control.
 */
class FinanceAuditWorkspaceController extends Controller
{
    /** type => [route name, route param, view permission] */
    private const SOURCE_ROUTES = [
        'invoice' => ['finance.invoices.show', 'invoice', 'view_finance_invoices'],
        'charge' => ['finance.charges.show', 'charge', 'view_finance_charges'],
        'payment' => ['finance.payments.show', 'payment', 'view_finance_payment_details'],
        'dng' => ['finance.dng.payment-requests.show', 'dngPaymentRequest', 'view_finance_dng_payment_requests'],
        'student' => ['finance.students.charges', 'student', 'view_finance_charges'],
    ];

    public function index(
        FinanceAuditSearchRequest $request,
        ResolveFinanceAuditSearchQuery $resolver,
        GetFinanceAuditGraphQuery $graphQuery,
        FinanceLedgerTimelineBuilder $timelineBuilder,
        FinanceAuditWarningBuilder $warningBuilder,
        FinanceInvariantRegistry $registry,
        FinanceIntegrityAuditor $auditor,
        FinanceInvariantSampleResolver $sampleResolver,
    ): Response|RedirectResponse {
        $campusId = $this->currentCampusId();
        $data = $request->validated();

        [$data, $redirectUrl] = $this->applyFindingDrilldown($data, $campusId, $registry, $auditor, $sampleResolver, $request);
        if ($redirectUrl !== null) {
            return redirect($redirectUrl);
        }

        $resolution = $this->resolve($data, $campusId, $resolver);
        $semesterId = isset($data['semester_id']) ? (int) $data['semester_id'] : null;
        $billingCycleId = isset($data['billing_cycle_id']) ? (int) $data['billing_cycle_id'] : null;

        $props = [
            'filters' => [
                'q' => $data['q'] ?? null,
                'target_type' => $data['target_type'] ?? null,
                'target_id' => isset($data['target_id']) ? (int) $data['target_id'] : null,
                'semester_id' => $semesterId,
                'billing_cycle_id' => $billingCycleId,
                'finding_code' => $data['finding_code'] ?? null,
                'sample_id' => isset($data['sample_id']) ? (int) $data['sample_id'] : null,
                'scope' => $data['scope'] ?? null,
            ],
            'resolution' => $resolution,
            'links' => $this->links($resolution, $request),
            'allowed_actions' => $this->allowedActions($request),
        ];

        if ($resolution['status'] === 'single') {
            $target = $resolution['target'];

            // Memoized per-request so the grouped graph + timeline deferred props
            // build the graph once. Each deferred closure re-checks campus.
            $graphMemo = null;
            $buildGraph = function () use (&$graphMemo, $target, $campusId, $semesterId, $billingCycleId, $graphQuery): array {
                if ($graphMemo !== null) {
                    return $graphMemo;
                }
                if (! $this->targetVisibleInCampus($target, $campusId)) {
                    return $graphMemo = $graphQuery->handle(['type' => 'student', 'id' => 0]);
                }

                return $graphMemo = $graphQuery->handle($target, $semesterId, $billingCycleId);
            };

            $props['graph'] = Inertia::defer($buildGraph);
            $props['timeline'] = Inertia::defer(fn () => $timelineBuilder->build($buildGraph()));
            $props['warnings'] = Inertia::defer(function () use ($target, $campusId, $warningBuilder): array {
                if (! $this->targetVisibleInCampus($target, $campusId)) {
                    return [];
                }
                $studentId = $this->ownerStudentId($target);

                return $studentId !== null
                    ? $warningBuilder->build(new FinanceAuditScope(studentIds: [$studentId]))
                    : [];
            });
        }

        return Inertia::render('Finance/Audit/Workspace', $props);
    }

    /**
     * Resolve either an explicit (campus-checked) target from the URL, or the
     * free-form search string.
     *
     * @param  array<string,mixed>  $data
     */
    private function resolve(array $data, ?int $campusId, ResolveFinanceAuditSearchQuery $resolver): array
    {
        if (! empty($data['target_type']) && ! empty($data['target_id'])) {
            $target = ['type' => (string) $data['target_type'], 'id' => (int) $data['target_id']];

            return $this->targetVisibleInCampus($target, $campusId)
                ? ['status' => 'single', 'target' => $target, 'matches' => []]
                : ['status' => 'empty', 'target' => null, 'matches' => []];
        }

        return $resolver->handle($data['q'] ?? null, $campusId);
    }

    private function ownerStudentId(array $target): ?int
    {
        $type = (string) ($target['type'] ?? '');
        $id = (int) ($target['id'] ?? 0);

        $studentId = match ($type) {
            'student' => $id,
            'invoice' => StudentInvoice::find($id)?->student_id,
            'payment' => Payment::find($id)?->student_id,
            'charge' => FinanceCharge::find($id)?->student_id,
            'dng' => DngPaymentRequest::find($id)?->student_id,
            default => null,
        };

        return $studentId !== null ? (int) $studentId : null;
    }

    private function targetVisibleInCampus(array $target, ?int $campusId): bool
    {
        if ($campusId === null) {
            return false;
        }

        $studentId = $this->ownerStudentId($target);
        if ($studentId === null) {
            return false;
        }

        $ownerCampusId = Student::find($studentId)?->campus_id;

        return $ownerCampusId !== null && (int) $ownerCampusId === $campusId;
    }

    /** @return array{source:?array{url:string,label:string}} */
    private function links(array $resolution, FinanceAuditSearchRequest $request): array
    {
        if ($resolution['status'] !== 'single') {
            return ['source' => null];
        }

        $target = $resolution['target'];
        $config = self::SOURCE_ROUTES[$target['type']] ?? null;
        if ($config === null) {
            return ['source' => null];
        }

        [$routeName, $param, $permission] = $config;
        if (! Route::has($routeName) || ! ($request->user()?->can($permission) ?? false)) {
            return ['source' => null];
        }

        return ['source' => [
            'url' => route($routeName, [$param => $target['id']]),
            'label' => 'Open source page',
        ]];
    }

    /** @return array{export:bool} export endpoint deferred — flag drives a disabled affordance. */
    private function allowedActions(FinanceAuditSearchRequest $request): array
    {
        return [
            'export' => $request->user()?->can('export_finance_audit_workspace') ?? false,
        ];
    }

    private function currentCampusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }

        $campus = app('campus');

        return $campus?->id !== null ? (int) $campus->id : null;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{0:array<string,mixed>,1:?string}
     */
    private function applyFindingDrilldown(
        array $data,
        ?int $campusId,
        FinanceInvariantRegistry $registry,
        FinanceIntegrityAuditor $auditor,
        FinanceInvariantSampleResolver $resolver,
        FinanceAuditSearchRequest $request,
    ): array {
        $code = $data['finding_code'] ?? null;
        if ($code === null) {
            return [$data, null];
        }

        $canAllCampus = $request->user()?->can('view_finance_all_campus') ?? false;
        if (($data['scope'] ?? 'campus') === 'all_campus' && ! $canAllCampus) {
            $data['scope'] = 'campus';
        }

        $listUrl = $resolver->listUrlFor($code);
        $targetType = $resolver->targetTypeFor($code);
        if ($targetType === null) {
            return [$data, $listUrl];
        }

        $sampleId = isset($data['sample_id']) ? (int) $data['sample_id'] : null;
        if ($sampleId === null) {
            $invariant = $registry->find($code);
            $scope = ($data['scope'] ?? 'campus') === 'all_campus'
                ? null
                : new FinanceAuditScope($this->campusStudentIds($campusId));
            $sampleId = $invariant !== null ? ($auditor->samples($invariant, $scope)[0] ?? null) : null;
        }

        if ($sampleId === null) {
            return [$data, null];
        }

        $targetId = $resolver->resolveTargetId($code, $sampleId);
        if ($targetId === null) {
            return [$data, $listUrl];
        }

        $data['target_type'] = $targetType;
        $data['target_id'] = $targetId;

        return [$data, null];
    }

    /** @return list<int> */
    private function campusStudentIds(?int $campusId): array
    {
        if ($campusId === null) {
            return [];
        }

        return Student::query()->where('campus_id', $campusId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
