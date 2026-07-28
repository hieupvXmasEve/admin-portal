<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Enums\StudentActionType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Actions\ImportStudentActionsFromExcelAction;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Exports\StudentActionImportTemplateExport;
use App\Modules\Academic\Exports\StudentActionLogsExport;
use App\Modules\Academic\Http\Requests\ExecuteStudentActionsImportRequest;
use App\Modules\Academic\Http\Requests\PreviewStudentActionsImportRequest;
use App\Modules\Academic\Progression\Http\Requests\ExportStudentActionAuditRequest;
use App\Modules\Academic\Progression\Http\Requests\ListStudentActionAuditRequest;
use App\Modules\Academic\Queries\ListStudentActionLogsQuery;
use App\Services\ExcelExportService;
use App\Shared\Contracts\Identity\DTO\UserDirectoryEntry;
use App\Shared\Contracts\Identity\UserDirectoryReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentActionAuditController extends Controller
{
    public function __construct(
        private readonly GetSemesterReferenceOptionsQuery $semesterOptions,
        private readonly CampusReferenceReader $campuses,
        private readonly UserDirectoryReader $userDirectory,
    ) {}

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
        $semesterCodes = $this->semesterOptions->codes();
        $campusCodes = array_values(array_filter(array_map(
            static fn (CampusReference $campus): string => $campus->code,
            $this->campuses->all(),
        ), static fn (string $code): bool => $code !== ''));

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

        return ApiResponse::compatible([
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
            return ApiResponse::compatible([
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

        return ApiResponse::compatible([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Display the audit listing page.
     */
    public function index(ListStudentActionAuditRequest $request, ListStudentActionLogsQuery $query): Response
    {
        $validated = $request->validated();

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
                'semesters' => $this->semesterOptions->options(),
                'actors' => array_map(
                    static fn (UserDirectoryEntry $actor): array => $actor->toArray(),
                    $this->userDirectory->staffMembers(),
                ),
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
    public function export(ExportStudentActionAuditRequest $request, ListStudentActionLogsQuery $query, ExcelExportService $excelService): BinaryFileResponse
    {
        $validated = $request->validated();

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
