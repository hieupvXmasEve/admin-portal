<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use App\Modules\Finance\Exports\NonAcademicChargesTemplateExport;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStatsQuery;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStudentsQuery;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\GetDueItemsSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListBillingExceptionsQuery;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use App\Modules\Finance\Queries\Operations\ListExamResitHandoffQuery;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BillingOperationsController extends Controller
{
    public function dashboard(
        Request $request,
        GetBillingDashboardStatsQuery $statsQuery,
        GetBillingDashboardStudentsQuery $studentsQuery
    ): Response {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'stage' => 'nullable|string',
            'defer' => 'nullable|string',
            'retake' => 'nullable|string',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
            'sort' => 'nullable|string',
            'direction' => 'nullable|string|in:asc,desc',
        ]);

        $semesterId = FinanceSemesterContextResolver::selectedId();
        $currentSemesterObj = $semesterId ? Semester::find($semesterId) : null;

        $kpiStats = $statsQuery->handle($semesterId);
        $students = $studentsQuery->handle($semesterId, $validated);
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/Operations/Dashboard', [
            'kpiStats' => $kpiStats,
            'students' => $students,
            'semesters' => $semesters,
            'currentSemester' => $currentSemesterObj,
            'freshness' => [
                'mode' => 'live_owner_reads',
                'as_of' => now()->toIso8601String(),
            ],
            'filters' => [
                'status' => $validated['status'] ?? 'all',
                'stage' => $validated['stage'] ?? 'all',
                'defer' => $validated['defer'] ?? 'all',
                'retake' => $validated['retake'] ?? 'all',
                'search' => $validated['search'] ?? '',
                'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : 20,
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
            ],
        ]);
    }

    public function showGenerateCharges(Request $request): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get();
        $currentCampus = app()->bound('campus') ? app('campus') : null;

        return Inertia::render('Finance/Operations/GenerateCharges', [
            'feeTypes' => NonAcademicChargeTypeEnum::forSelect(),
            'semesters' => $semesters,
            'currentCampus' => $currentCampus ? ['id' => $currentCampus->id, 'name' => $currentCampus->name ?? null] : null,
        ]);
    }

    /**
     * Download the CSV template for non-academic charge generation.
     *
     * Requires the same permission as the generate-charges page (view_finance_operations_generate_charges).
     * Returns a CSV file with a single `student_code` column and one example row.
     */
    public function downloadNonAcademicChargesTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new NonAcademicChargesTemplateExport,
            'non_academic_charges_template.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function exceptions(
        Request $request,
        GetBillingExceptionCountsQuery $countsQuery,
        ListBillingExceptionsQuery $listQuery
    ): Response {
        $validated = $request->validate([
            'type' => 'nullable|string',
        ]);

        [$semesterId, $semesterFilter] = $this->resolveExceptionsSemesterFilter();
        $type = $validated['type'] ?? 'all';

        $currentSemester = $semesterId ? Semester::find($semesterId) : null;

        $counts = $countsQuery->handle($semesterId);
        $exceptions = $listQuery->handle($semesterId, $type, $request->url());
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/Operations/ExceptionsQueue', [
            'exceptions' => $exceptions,
            'counts' => $counts,
            'semesters' => $semesters,
            'currentSemester' => $currentSemester,
            'filters' => [
                'semester_id' => $semesterFilter,
                'type' => $type,
            ],
        ]);
    }

    public function dueCalendar(
        Request $request,
        GetDueItemsSummaryQuery $summaryQuery,
        ListDueItemsQuery $listQuery,
        ListExamResitHandoffQuery $handoffQuery
    ): Response {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'source' => 'nullable|string|in:dng_request,exam_resit',
            'fee_type' => 'nullable|string',
        ]);

        // Semester is driven by the global top-bar SemesterSwitcher (session-backed,
        // active-semester fallback) — same source as the shared `semester` prop —
        // not a per-page select.
        $semesterId = FinanceSemesterContextResolver::selectedId();
        $status = $validated['status'] ?? 'all';
        $search = $validated['search'] ?? '';
        $source = $validated['source'] ?? null;
        $feeType = $validated['fee_type'] ?? null;

        $summary = $summaryQuery->handle($semesterId);
        $invoices = $listQuery->handle($semesterId, $status, $search, $source, $feeType);

        // Exam-resit (PTL) sources that are overdue but still need charge/DNG
        // creation are surfaced as a handoff list, unless the staff is filtering
        // to plain DNG-request rows only.
        $handoff = $source === ListDueItemsQuery::SOURCE_DNG_REQUEST
            ? null
            : $handoffQuery->handle($semesterId, $search);

        return Inertia::render('Finance/Operations/DueCalendar', [
            'invoices' => $invoices,
            'handoffItems' => $handoff,
            'summary' => $summary,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'source' => $source ?? 'all',
                'fee_type' => $feeType ?? 'all',
            ],
        ]);
    }

    public function exportDueList()
    {
        return response()->json(['message' => 'Export not yet implemented']);
    }

    /**
     * @return array{0: ?int, 1: ?string} [semesterId for queries, filter value for UI]
     */
    private function resolveExceptionsSemesterFilter(): array
    {
        $selectedId = FinanceSemesterContextResolver::selectedId();

        return [
            $selectedId,
            $selectedId !== null ? (string) $selectedId : null,
        ];
    }
}
