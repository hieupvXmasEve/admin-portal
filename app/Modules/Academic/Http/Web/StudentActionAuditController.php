<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Enums\StudentActionType;
use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Academic\Actions\ImportStudentActionsFromExcelAction;
use App\Modules\Academic\Exports\StudentActionImportTemplateExport;
use App\Modules\Academic\Exports\StudentActionLogsExport;
use App\Modules\Academic\Http\Requests\ExecuteStudentActionsImportRequest;
use App\Modules\Academic\Http\Requests\PreviewStudentActionsImportRequest;
use App\Modules\Academic\Queries\ListStudentActionLogsQuery;
use App\Services\ExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentActionAuditController extends Controller
{
    public function importPage(): Response
    {
        return Inertia::render('Admin/Reports/StudentActionsImport');
    }

    public function downloadImportTemplate(ExcelExportService $excelService): BinaryFileResponse
    {
        $actionTypes = collect(StudentActionType::cases())
            ->reject(fn (StudentActionType $type) => $type === StudentActionType::ADMISSION_DEFERRAL)
            ->map(fn (StudentActionType $type) => $type->value)
            ->values()
            ->all();
        $semesterCodes = Semester::query()
            ->whereNotNull('code')
            ->orderBy('start_date', 'desc')
            ->pluck('code')
            ->values()
            ->all();
        $campusCodes = Campus::query()
            ->whereNotNull('code')
            ->orderBy('name')
            ->pluck('code')
            ->values()
            ->all();

        return $excelService->download(
            new StudentActionImportTemplateExport(
                $actionTypes,
                $semesterCodes,
                $campusCodes
            ),
            'student_actions_import_template'
        );
    }

    public function previewImport(
        PreviewStudentActionsImportRequest $request,
        ImportStudentActionsFromExcelAction $action
    ): JsonResponse {
        $sharedUploadRecordId = $request->filled('shared_upload_record_id')
            ? (int) $request->input('shared_upload_record_id')
            : null;
        $result = $action->preview(
            $request->file('file'),
            $sharedUploadRecordId
        );
        $previewToken = (string) Str::uuid();
        Cache::put(
            sprintf('student-action-import-preview:%d:%s', $request->user()->id, $previewToken),
            [
                'file_hash' => hash_file('sha256', $request->file('file')->getRealPath()),
                'shared_upload_record_id' => $sharedUploadRecordId,
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'success' => true,
            'data' => [
                ...$result,
                'preview_token' => $previewToken,
            ],
        ]);
    }

    public function executeImport(
        ExecuteStudentActionsImportRequest $request,
        ImportStudentActionsFromExcelAction $action
    ): JsonResponse {
        $previewToken = (string) $request->input('preview_token');
        $cacheKey = sprintf('student-action-import-preview:%d:%s', $request->user()->id, $previewToken);
        $cachedPreview = Cache::get($cacheKey);
        $sharedUploadRecordId = $request->filled('shared_upload_record_id')
            ? (int) $request->input('shared_upload_record_id')
            : null;
        $currentHash = hash_file('sha256', $request->file('file')->getRealPath());

        if (! $cachedPreview
            || ($cachedPreview['shared_upload_record_id'] ?? null) !== $sharedUploadRecordId
            || ($cachedPreview['file_hash'] ?? null) !== $currentHash) {
            return response()->json([
                'success' => false,
                'message' => 'Preview token is invalid or file does not match preview.',
            ], 422);
        }

        $result = $action->execute(
            $request->file('file'),
            $sharedUploadRecordId,
            (int) $request->user()->id
        );
        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

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
            'egc_defer_from_block_number' => ['nullable', 'integer', 'in:1,2'],
            'return_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'dropout_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'effective_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'missing_documents' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentCampusId = session('current_campus_id');
        $filters = array_merge([
            'per_page' => 15,
        ], array_filter($validated, fn ($v) => $v !== null && $v !== ''), [
            'student_campus_id' => $currentCampusId,
        ]);

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
                'from_semester_id' => $filters['from_semester_id'] ?? null,
                'egc_defer_from_block_number' => $filters['egc_defer_from_block_number'] ?? null,
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
                'actors' => User::query()
                    ->select('id', 'name', 'email')
                    ->where('type', 'staff')
                    ->orderBy('name')
                    ->get(),
                'egcDeferBlocks' => [
                    ['value' => 1, 'label' => 'Block 1'],
                    ['value' => 2, 'label' => 'Block 2'],
                ],
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
            'from_semester_id' => ['nullable', 'integer'],
            'egc_defer_from_block_number' => ['nullable', 'integer', 'in:1,2'],
            'return_semester_id' => ['nullable', 'integer'],
            'dropout_semester_id' => ['nullable', 'integer'],
            'effective_semester_id' => ['nullable', 'integer'],
            'actor_id' => ['nullable', 'integer'],
            'missing_documents' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $filters = array_merge(
            array_filter($validated, fn ($v) => $v !== null && $v !== ''),
            ['student_campus_id' => session('current_campus_id')]
        );

        // Get the builder with filters applied
        $builder = $query->getBuilder($filters);

        $export = new StudentActionLogsExport($builder);
        $filename = 'student_actions_audit_'.date('Y-m-d_H-i');

        return $excelService->download($export, $filename);
    }
}
