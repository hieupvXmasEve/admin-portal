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
            'semester_id' => 'nullable|integer|exists:semesters,id',
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

        $currentSemester = Semester::where('is_active', true)->first();

        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : ($currentSemester?->id);

        $currentSemesterObj = $semesterId ? Semester::find($semesterId) : $currentSemester;

        $kpiStats = $statsQuery->handle($semesterId);
        $students = $studentsQuery->handle($semesterId, $validated);
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/Operations/Dashboard', [
            'kpiStats' => $kpiStats,
            'students' => $students,
            'semesters' => $semesters,
            'currentSemester' => $currentSemesterObj,
            'filters' => [
                'semester_id' => $semesterId ? (string) $semesterId : null,
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
            'semester_id' => 'nullable',
            'type' => 'nullable|string',
        ]);

        [$semesterId, $semesterFilter] = $this->resolveExceptionsSemesterFilter($request);
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
        ListDueItemsQuery $listQuery
    ): Response {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        $status = $validated['status'] ?? 'all';
        $search = $validated['search'] ?? '';

        $currentSemester = $semesterId
            ? Semester::find($semesterId)
            : Semester::where('is_active', true)->first();

        $summary = $summaryQuery->handle($semesterId);
        $invoices = $listQuery->handle($semesterId, $status, $search);
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/Operations/DueCalendar', [
            'invoices' => $invoices,
            'summary' => $summary,
            'semesters' => $semesters,
            'currentSemester' => $currentSemester,
            'filters' => [
                'semester_id' => $semesterId ? (string) $semesterId : null,
                'status' => $status,
                'search' => $search,
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
    private function resolveExceptionsSemesterFilter(Request $request): array
    {
        if (! $request->has('semester_id')) {
            $selectedId = $this->resolveSelectedSemesterId();

            return [
                $selectedId,
                $selectedId !== null ? (string) $selectedId : null,
            ];
        }

        $raw = $request->query('semester_id');
        if ($raw === null || $raw === '' || $raw === 'all') {
            return [null, 'all'];
        }

        $id = filter_var($raw, FILTER_VALIDATE_INT);
        if ($id === false || ! Semester::query()->whereKey($id)->exists()) {
            $selectedId = $this->resolveSelectedSemesterId();

            return [
                $selectedId,
                $selectedId !== null ? (string) $selectedId : null,
            ];
        }

        return [(int) $id, (string) $id];
    }

    private function resolveSelectedSemesterId(): ?int
    {
        $selectedId = session('current_semester_id');

        if ($selectedId !== null) {
            return (int) $selectedId;
        }

        $activeId = Semester::query()->where('is_active', true)->value('id');

        return $activeId !== null ? (int) $activeId : null;
    }
}
