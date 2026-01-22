<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStatsQuery;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStudentsQuery;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\GetDueInvoicesSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListBillingExceptionsQuery;
use App\Modules\Finance\Queries\Operations\ListDueInvoicesQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class   BillingOperationsController extends Controller
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
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
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
                'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : 20,
            ],
        ]);
    }

    public function showGenerateCharges(Request $request): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get();
        $currentSemester = Semester::where('is_active', true)->first();

        // Updated Charge Types based on Use Cases
        $chargeTypes = [
            [
                'value' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
                'label' => 'GC Fee (EGC Level)',
                'description' => 'Áp dụng cho sinh viên status "intake_pre_uni_gc"',
                'is_credit' => false,
            ],
            [
                'value' => FinanceCharge::TYPE_TUITION_TERM,
                'label' => 'Course Tuition Fee (Học phí khóa)',
                'description' => 'Áp dụng cho sinh viên status "intake_course"',
                'is_credit' => false,
            ],
            [
                'value' => 'voucher',
                'label' => 'Voucher',
                'description' => 'Tự động áp dụng voucher khả dụng (chưa dùng)',
                'is_credit' => true,
            ],
            // Retake fee can be added if logic supports it, currently logic focuses on status-based fee.
            // Keeping it out for now as prompt focused on status-based generation.
        ];

        return Inertia::render('Finance/Operations/GenerateCharges', [
            'semesters' => $semesters,
            'chargeTypes' => $chargeTypes,
            'currentSemester' => $currentSemester,
        ]);
    }

    public function exceptions(
        Request $request,
        GetBillingExceptionCountsQuery $countsQuery,
        ListBillingExceptionsQuery $listQuery
    ): Response {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'type' => 'nullable|string',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        $type = $validated['type'] ?? 'all';

        $currentSemester = $semesterId
            ? Semester::find($semesterId)
            : Semester::where('is_active', true)->first();

        $counts = $countsQuery->handle($semesterId);
        $exceptions = $listQuery->handle($semesterId, $type, $request->url());
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/Operations/ExceptionsQueue', [
            'exceptions' => $exceptions,
            'counts' => $counts,
            'semesters' => $semesters,
            'currentSemester' => $currentSemester,
            'filters' => [
                'semester_id' => $semesterId ? (string) $semesterId : null,
                'type' => $type,
            ],
        ]);
    }

    public function dueCalendar(
        Request $request,
        GetDueInvoicesSummaryQuery $summaryQuery,
        ListDueInvoicesQuery $listQuery
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
}
