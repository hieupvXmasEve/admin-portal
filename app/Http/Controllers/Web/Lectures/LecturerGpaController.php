<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Lectures;

use App\Exports\LecturerGpaReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\ListLecturerGpaRequest;
use App\Models\Semester;
use App\Queries\Lecture\ListLecturerGpaQuery;
use App\Services\ExcelExportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LecturerGpaController extends Controller
{
    public function index(ListLecturerGpaRequest $request, ListLecturerGpaQuery $query): Response
    {
        $currentCampusId = (int) session('current_campus_id');
        $filters = $this->normalizeFilters($request->validated());
        $selectedSemester = $this->selectedSemester($filters['semester_id']);

        return Inertia::render('lectures/LecturerGpa', [
            'rows' => $query->paginate($filters, $currentCampusId),
            'filters' => $filters,
            'semesters' => $this->semesterOptions(),
            'active_semester' => $selectedSemester,
        ]);
    }

    public function export(
        ListLecturerGpaRequest $request,
        ListLecturerGpaQuery $query,
        ExcelExportService $excelService,
    ): BinaryFileResponse {
        $currentCampusId = (int) session('current_campus_id');
        $filters = $this->normalizeFilters($request->validated());
        $semester = $this->selectedSemester($filters['semester_id']);
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
    private function normalizeFilters(array $validated): array
    {
        return [
            'search' => $validated['search'] ?? '',
            'semester_id' => $this->resolveSemesterId($validated['semester_id'] ?? null),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'sort' => $validated['sort'] ?? 'lecturer_name',
            'direction' => $validated['direction'] ?? 'asc',
        ];
    }

    private function resolveSemesterId(null|string|int $semesterId): ?string
    {
        if ($semesterId !== null && $semesterId !== '') {
            return (string) $semesterId;
        }

        $activeSemester = Semester::getActiveSemester();
        if ($activeSemester !== null) {
            return (string) $activeSemester->id;
        }

        $latestSemester = Semester::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        return $latestSemester ? (string) $latestSemester->id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedSemester(?string $semesterId): ?array
    {
        if ($semesterId === null) {
            return null;
        }

        $semester = Semester::query()->find((int) $semesterId);

        if ($semester === null) {
            return null;
        }

        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'code' => $semester->code,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, code: string|null}>
     */
    private function semesterOptions(): array
    {
        return Semester::query()
            ->select('id', 'name', 'code')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Semester $semester): array => [
                'id' => $semester->id,
                'name' => $semester->name,
                'code' => $semester->code,
            ])
            ->all();
    }
}
