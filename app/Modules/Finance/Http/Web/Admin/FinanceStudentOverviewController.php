<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Modules\Finance\Http\Requests\Student360ShowRequest;
use App\Modules\Finance\Queries\Audit\GetFinanceAuditGraphQuery;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Queries\Student360\GetStudent360LedgerQuery;
use App\Modules\Finance\Queries\Student360\GetStudent360StatusCardsQuery;
use App\Modules\Finance\Queries\Student360\GetStudentFinanceOverviewKpisQuery;
use App\Modules\Finance\Support\Audit\FinanceLedgerTimelineBuilder;
use App\Modules\Finance\Support\LifecycleDueExceptionReasonResolver;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only Student 360 shell. Identity + four derived balances + a deferred
 * signed ledger timeline. Reuses existing Finance read models — no money math
 * here. Campus-scoped: a student outside the current campus is 404 unless the
 * user holds `view_finance_all_campus`.
 */
class FinanceStudentOverviewController extends Controller
{
    public function show(
        Student $student,
        Student360ShowRequest $request,
        GetStudentBalanceQuery $balanceQuery,
        GetFinanceAuditGraphQuery $graphQuery,
        FinanceLedgerTimelineBuilder $timelineBuilder,
        GetStudent360StatusCardsQuery $cardsQuery,
        GetStudent360LedgerQuery $ledgerQuery,
        GetStudentFinanceOverviewKpisQuery $tuitionOverviewQuery,
    ): Response {
        if (! $this->visible($student, $request)) {
            abort(404);
        }

        $studentId = (int) $student->id;
        $balance = $balanceQuery->handle($studentId); // student-level truth, all semesters
        $reason = LifecycleDueExceptionReasonResolver::resolve($student);

        $props = [
            'student' => [
                'id' => $studentId,
                'student_code' => $student->student_id,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'academic_status' => $student->academic_status,
                'lifecycle_reason' => $reason->value,
                'lifecycle_label' => $reason->label(),
            ],
            'balances' => [
                'net_charges' => $balance['net_charges'],
                'total_paid' => $balance['total_paid'],
                'balance' => $balance['balance'],
                'unapplied_credit' => $balance['unapplied_credit'],
                'status' => $balance['status'],
            ],
            'tuition_overview' => $tuitionOverviewQuery->handle($studentId),
            'focus' => $request->focusTarget(),
            'links' => [
                'audit' => route('finance.audit.index', ['target_type' => 'student', 'target_id' => $studentId]),
            ],
        ];

        $props['status_cards'] = $cardsQuery->handle($studentId);

        $props['actions'] = [
            'can_record_payment' => $request->user()?->can('create_finance_payments') ?? false,
            'can_allocate' => $request->user()?->can('allocate_finance_payment') ?? false,
            'can_cancel_dng' => $request->user()?->can('create_finance_payments') ?? false,
            'can_void_charges' => $request->user()?->can('void_finance_charges') ?? false,
        ];

        // Basic ledger is heavy → deferred. Reuses the audit money-graph + signed
        // timeline builder so the 360 ledger and Audit Workspace never diverge.
        $props['ledger'] = Inertia::defer(function () use ($studentId, $graphQuery, $timelineBuilder): array {
            $graph = $graphQuery->handle(['type' => 'student', 'id' => $studentId]);

            return $timelineBuilder->build($graph);
        });

        // Grouped "Sổ cái" lens is heavier than the four cards → deferred.
        $props['ledger_groups'] = Inertia::defer(fn () => $ledgerQuery->handle($studentId));

        return Inertia::render('Finance/Student360/Show', $props);
    }

    private function visible(Student $student, Student360ShowRequest $request): bool
    {
        $campusId = $this->currentCampusId();
        if ($campusId !== null && (int) $student->campus_id === $campusId) {
            return true;
        }

        return $request->user()?->can('view_finance_all_campus') ?? false;
    }

    private function currentCampusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }

        $campus = app('campus');

        return $campus?->id !== null ? (int) $campus->id : null;
    }
}
