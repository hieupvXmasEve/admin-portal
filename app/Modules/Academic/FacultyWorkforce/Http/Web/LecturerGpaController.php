<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Http\Web;

use App\Exports\LecturerGpaReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\ListLecturerGpaRequest;
use App\Modules\Academic\Catalog\Queries\GetLecturerGpaSemesterContextQuery;
use App\Queries\Lecture\ListLecturerGpaQuery;
use App\Services\ExcelExportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LecturerGpaController extends Controller
{
    public function index(
        ListLecturerGpaRequest $request,
        ListLecturerGpaQuery $query,
        GetLecturerGpaSemesterContextQuery $semesters,
    ): Response {
        $currentCampusId = (int) session('current_campus_id');
        $filters = $this->normalizeFilters($request->validated(), $semesters);
        $selectedSemester = $filters['semester_id'] !== null ? $semesters->find((int) $filters['semester_id']) : null;

        return Inertia::render('Lectures/LecturerGpa', [
            'rows' => $query->paginate($filters, $currentCampusId),
            'filters' => $filters,
            'semesters' => $semesters->listAll(),
            'active_semester' => $selectedSemester,
        ]);
    }

    public function export(
        ListLecturerGpaRequest $request,
        ListLecturerGpaQuery $query,
        ExcelExportService $excelService,
        GetLecturerGpaSemesterContextQuery $semesters,
    ): BinaryFileResponse {
        $currentCampusId = (int) session('current_campus_id');
        $filters = $this->normalizeFilters($request->validated(), $semesters);
        $semester = $filters['semester_id'] !== null ? $semesters->find((int) $filters['semester_id']) : null;
        $rows = $query->all($filters, $currentCampusId);

        $filenamePrefix = 'lecturer_gpa';
        if ($semester !== null && ! empty($semester['code'])) {
            $filenamePrefix .= '_'.$semester['code'];
        }

        $fileName = $excelService->generateFilenameWithTimestamp($filenamePrefix);

        Log::info('Lecturer GPA export completed successfully', [
            'user_id' => Auth::id(),
            'campus_id' => $currentCampusId,
            'semester_id' => $filters['semester_id'],
            'rows_count' => $rows->count(),
            'file_name' => $fileName,
        ]);

        return $excelService->download(
            new LecturerGpaReportExport($rows, $semester),
            $fileName,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $validated, GetLecturerGpaSemesterContextQuery $semesters): array
    {
        return [
            'search' => $validated['search'] ?? '',
            'semester_id' => $this->resolveSemesterId($validated['semester_id'] ?? null, $semesters),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'sort' => $validated['sort'] ?? 'lecturer_name',
            'direction' => $validated['direction'] ?? 'asc',
        ];
    }

    private function resolveSemesterId(null|string|int $semesterId, GetLecturerGpaSemesterContextQuery $semesters): ?string
    {
        if ($semesterId !== null && $semesterId !== '') {
            return (string) $semesterId;
        }

        return $semesters->resolveActiveOrLatestId();
    }
}
