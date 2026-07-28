<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Exports\StudentLifecycleStatusExport;
use App\Modules\Academic\Progression\Http\Requests\ExportStudentLifecycleYearlyRequest;
use App\Modules\Academic\Progression\Http\Requests\ListStudentLifecycleYearlyRequest;
use App\Modules\Academic\Progression\Queries\Reporting\GetStudentLifecycleCohortMatrixQuery;
use App\Modules\Academic\Progression\Queries\Reporting\GetStudentStatusBySemesterQuery;
use App\Services\ExcelExportService;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentLifecycleYearlyAnalysisController extends Controller
{
    public function __construct(
        private readonly GetStudentLifecycleCohortMatrixQuery $cohortMatrix,
        private readonly GetStudentStatusBySemesterQuery $statusBySemesterQuery,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly GetSemesterReferenceOptionsQuery $semesterOptions,
    ) {}

    public function index(ListStudentLifecycleYearlyRequest $request): Response
    {
        $validated = $request->validated();

        $currentCampusId = session('current_campus_id');
        $matrix = $this->cohortMatrix->handle($currentCampusId);

        $semesterOptions = $this->semesterOptions->lifecycleOptions();

        $defaultSemesterId = $this->academicPeriods->current()?->id;

        if (! $defaultSemesterId) {
            $defaultSemesterId = $this->semesterOptions->latestId();
        }

        $selectedSemesterId = (int) ($validated['selected_semester_id'] ?? $defaultSemesterId ?? 0);
        $currentStatus = $validated['current_status'] ?? null;
        $statusPerPage = (int) ($validated['status_per_page'] ?? 25);
        $cohortSemesterId = isset($validated['cohort_semester_id']) ? (int) $validated['cohort_semester_id'] : null;

        $statusTable = $selectedSemesterId > 0
            ? $this->statusBySemesterQuery->handle($selectedSemesterId, $currentCampusId, $currentStatus, $statusPerPage, $cohortSemesterId)
            : null;

        return Inertia::render('Admin/Reports/StudentLifecycleYearlyAnalysis/Index', [
            'matrix' => $matrix,
            'statusTable' => $statusTable,
            'statusFilters' => [
                'selected_semester_id' => $selectedSemesterId > 0 ? $selectedSemesterId : null,
                'current_status' => $currentStatus,
                'cohort_semester_id' => $cohortSemesterId,
                'status_per_page' => $statusPerPage,
                'page' => (int) ($validated['page'] ?? 1),
            ],
            'statusOptions' => [
                'semesters' => $semesterOptions,
                'statuses' => [
                    ['value' => 'pending', 'label' => 'Pending'],
                    // ['value' => 'admission_deferred', 'label' => 'Admission Deferred'],
                    ['value' => 'intake_pre_uni_gc', 'label' => 'Intake Pre-Uni GC'],
                    ['value' => 'intake_course', 'label' => 'Intake Course'],
                    ['value' => 'deferred', 'label' => 'Deferred'],
                    ['value' => 'dropout', 'label' => 'Dropout'],
                    ['value' => 'dropout_transfer', 'label' => 'Dropout Transfer'],
                    ['value' => 'graduated', 'label' => 'Graduated'],
                    // ['value' => 'active', 'label' => 'Active'],
                    // ['value' => 'inactive', 'label' => 'Inactive'],
                    // ['value' => 'suspended', 'label' => 'Suspended'],
                ],
            ],
            'meta' => [
                'campus_id' => $currentCampusId,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function export(ExportStudentLifecycleYearlyRequest $request, ExcelExportService $excelService): BinaryFileResponse
    {
        $validated = $request->validated();

        $selectedSemesterCode = $this->semesterOptions->codeOrFail((int) $validated['selected_semester_id']);
        $currentCampusId = session('current_campus_id');
        $currentStatus = $validated['current_status'] ?? null;

        $rows = $this->statusBySemesterQuery->handleExport(
            (int) $validated['selected_semester_id'],
            $currentCampusId,
            $currentStatus,
            isset($validated['cohort_semester_id']) ? (int) $validated['cohort_semester_id'] : null,
        );

        $filename = sprintf(
            'student_lifecycle_status_%s_%s',
            $selectedSemesterCode,
            now()->format('Y-m-d_H-i')
        );

        return $excelService->download(new StudentLifecycleStatusExport($rows), $filename);
    }
}
