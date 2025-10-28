<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exports\FailedStudentsExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\FailedStudentResource;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Unit;
use App\Services\ExcelExportService;
use App\Services\FailedStudentsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FailedStudentsController extends Controller
{
    public function __construct(
        private readonly FailedStudentsService $service,
        private readonly ExcelExportService $excelService
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'semester_id' => ['nullable', 'exists:semesters,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'attempt_number' => ['nullable', 'in:1,2,3+'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', 'string', 'in:student_id,student_name,unit_code,final_percentage,attendance_percentage,attempt_number'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        // Set default semester to active semester if not provided
        if (! isset($filters['semester_id'])) {
            $activeSemester = Semester::where('is_active', true)->first();
            if ($activeSemester) {
                $filters['semester_id'] = $activeSemester->id;
            }
        }

        $failedStudents = $this->service->getFailedStudents($filters);

        $paginatedData = [
            'data' => FailedStudentResource::collection($failedStudents->items())->resolve(),
            'current_page' => $failedStudents->currentPage(),
            'last_page' => $failedStudents->lastPage(),
            'per_page' => $failedStudents->perPage(),
            'total' => $failedStudents->total(),
            'from' => $failedStudents->firstItem(),
            'to' => $failedStudents->lastItem(),
            'prev_page_url' => $failedStudents->previousPageUrl(),
            'next_page_url' => $failedStudents->nextPageUrl(),
            'links' => $failedStudents->linkCollection()->toArray(),
        ];

        // Get summary statistics if semester is selected
        $summary = null;
        $failReasons = null;
        $unitDistribution = null;

        if ($filters['semester_id'] ?? null) {
            $summary = $this->service->getSummaryStatistics(
                (int) $filters['semester_id'],
                isset($filters['program_id']) ? (int) $filters['program_id'] : null
            );
            $failReasons = $this->service->getFailReasonDistribution(
                (int) $filters['semester_id'],
                isset($filters['program_id']) ? (int) $filters['program_id'] : null
            );
            $unitDistribution = $this->service->getFailedUnitDistribution(
                (int) $filters['semester_id'],
                isset($filters['program_id']) ? (int) $filters['program_id'] : null
            );
        }

        // Get filter options
        $semesters = Semester::where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code']);

        $programs = Program::orderBy('name')
            ->get(['id', 'name', 'code']);

        $units = Unit::orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('FailedStudents/Index', [
            'failed_students' => $paginatedData,
            'summary' => $summary,
            'fail_reasons' => $failReasons,
            'unit_distribution' => $unitDistribution,
            'semesters' => $semesters,
            'programs' => $programs,
            'units' => $units,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->validate([
            'semester_id' => ['nullable', 'exists:semesters,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'attempt_number' => ['nullable', 'in:1,2,3+'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        // Get all failed students without pagination for export
        $filters['per_page'] = 10000; // Large number to get all records
        $failedStudents = $this->service->getFailedStudents($filters);

        $export = new FailedStudentsExport(
            FailedStudentResource::collection($failedStudents->items())->resolve()
        );

        $semesterCode = 'all';
        if ($filters['semester_id'] ?? null) {
            $semester = Semester::find($filters['semester_id']);
            $semesterCode = $semester?->code ?? 'semester';
        }

        $filename = $this->excelService->generateFilenameWithTimestamp(
            "failed_students_{$semesterCode}"
        );

        return $this->excelService->download($export, $filename);
    }
}
