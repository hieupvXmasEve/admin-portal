<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Enums\StudentActionType;
use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Academic\Exports\StudentActionLogsExport;
use App\Modules\Academic\Queries\ListStudentActionLogsQuery;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentActionAuditController extends Controller
{
    /**
     * Display the audit listing page.
     */
    public function index(Request $request, ListStudentActionLogsQuery $query): Response
    {
        $validated = $request->validate([
            'action_type' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'signed_date_from' => ['nullable', 'date'],
            'signed_date_to' => ['nullable', 'date'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'from_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'return_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'dropout_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'effective_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'to_campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'from_campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'missing_documents' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentCampusId = session('current_campus_id');
        $filters = array_merge([
            'campus_id' => $currentCampusId,
            'per_page' => 15,
        ], array_filter($validated, fn($v) => $v !== null && $v !== ''));

        $actionLogs = $query->handle($filters);

        return Inertia::render('Admin/Reports/StudentActionsAudit', [
            'actionLogs' => $actionLogs,
            'filters' => [
                'action_type' => $filters['action_type'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'signed_date_from' => $filters['signed_date_from'] ?? null,
                'signed_date_to' => $filters['signed_date_to'] ?? null,
                'semester_id' => $filters['semester_id'] ?? null,
                'campus_id' => $filters['campus_id'] ?? null,
                'to_campus_id' => $filters['to_campus_id'] ?? null,
                'from_campus_id' => $filters['from_campus_id'] ?? null,
                'actor_id' => $filters['actor_id'] ?? null,
                'missing_documents' => $filters['missing_documents'] ?? null,
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? 'created_at',
                'direction' => $filters['direction'] ?? 'desc',
                'per_page' => $filters['per_page'],
            ],
            'options' => [
                'actionTypes' => StudentActionType::options(),
                'semesters' => Semester::query()
                    ->select('id', 'name', 'code')
                    ->orderBy('start_date', 'desc')
                    ->get(),
                'campuses' => Campus::query()
                    ->select('id', 'name', 'code')
                    ->orderBy('name')
                    ->get(),
                'actors' => User::query()
                    ->select('id', 'name', 'email')
                    ->where('type', 'staff')
                    ->orderBy('name')
                    ->get(),
            ],
        ]);
    }

    /**
     * Export the audit log as Excel/CSV.
     */
    public function export(Request $request, ListStudentActionLogsQuery $query, ExcelExportService $excelService): BinaryFileResponse
    {
        $validated = $request->validate([
            'action_type' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'signed_date_from' => ['nullable', 'date'],
            'signed_date_to' => ['nullable', 'date'],
            'semester_id' => ['nullable', 'integer'],
            'campus_id' => ['nullable', 'integer'],
            'to_campus_id' => ['nullable', 'integer'],
            'from_campus_id' => ['nullable', 'integer'],
            'actor_id' => ['nullable', 'integer'],
            'missing_documents' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $filters = array_filter($validated, fn($v) => $v !== null && $v !== '');

        // Get the builder with filters applied
        $builder = $query->getBuilder($filters);

        $export = new StudentActionLogsExport($builder);
        $filename = 'student_actions_audit_' . date('Y-m-d_H-i');

        return $excelService->download($export, $filename);
    }
}
