<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Http\Web;

use App\Actions\Lecture\GetTeachingHoursAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\ExportLecturesRequest;
use App\Http\Requests\Lecture\ViewTeachingHoursRequest;
use App\Http\Responses\ApiResponse;
use App\Services\LectureExcelExportService;
use App\Services\LectureTeachingHoursExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LectureExportController extends Controller
{
    public function __construct(
        private readonly LectureExcelExportService $exportService
    ) {}

    /**
     * Export all lecturers to Excel
     */
    public function exportExcel(): BinaryFileResponse|JsonResponse
    {
        try {
            $filePath = $this->exportService->exportLecturersToExcel();
            $fileName = 'lecturers_export_'.now()->format('Y-m-d').'.xlsx';

            Log::info('Lecturers export completed successfully', [
                'user_id' => Auth::id(),
                'file_name' => $fileName,
            ]);

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Lecturers export failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error('Export failed: '.$e->getMessage(), status: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export lecturers to Excel with current filters
     */
    public function exportExcelWithCurrentFilters(ExportLecturesRequest $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validated();

        try {
            // Build filters from validated request
            $filters = [];
            $currentCampusId = session('current_campus_id');

            if ($currentCampusId) {
                $filters['campus_id'] = (int) $currentCampusId;
            }

            // Handle search filter
            if ($request->filled('search')) {
                $filters['search'] = $validated['search'];
            }

            // Handle specific field filters
            if ($request->filled('campus_id')) {
                $filters['campus_id'] = $validated['campus_id'];
            }

            if ($request->filled('semester_id') && $validated['semester_id'] !== 'all') {
                $filters['semester_id'] = $validated['semester_id'];
            }

            if ($request->filled('unit_type') && $validated['unit_type'] !== 'all') {
                $filters['unit_type'] = $validated['unit_type'];
            }

            if ($request->filled('employment_status') && $validated['employment_status'] !== 'all') {
                $filters['employment_status'] = $validated['employment_status'];
            }

            if ($request->filled('employment_type') && $validated['employment_type'] !== 'all') {
                $filters['employment_type'] = $validated['employment_type'];
            }

            if ($request->filled('department') && $validated['department'] !== 'all') {
                $filters['department'] = $validated['department'];
            }

            if ($request->filled('academic_rank') && $validated['academic_rank'] !== 'all') {
                $filters['academic_rank'] = $validated['academic_rank'];
            }

            if ($request->has('available_for_assignment')) {
                $filters['available_for_assignment'] = $validated['available_for_assignment'];
            }

            if ($request->has('is_active')) {
                $filters['is_active'] = $validated['is_active'];
            }

            // Generate Excel file with filters
            $filePath = $this->exportService->exportLecturersToExcel($filters);

            // Generate download filename with filter info
            $filterSuffix = '';
            if (! empty($filters)) {
                $filterSuffix = '_filtered';
            }
            $fileName = 'lecturers_export'.$filterSuffix.'_'.now()->format('Y-m-d').'.xlsx';

            Log::info('Filtered lecturers export completed successfully', [
                'user_id' => Auth::id(),
                'filters' => $filters,
                'file_name' => $fileName,
            ]);

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Filtered lecturers export failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'filters' => $validated ?? [],
            ]);

            return ApiResponse::error('Export failed: '.$e->getMessage(), status: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export the teaching-hours summary using the current report filters.
     */
    public function exportTeachingHours(
        ViewTeachingHoursRequest $request,
        GetTeachingHoursAction $action,
        LectureTeachingHoursExcelExportService $exportService
    ): BinaryFileResponse|JsonResponse {
        $validated = $request->validated();
        $filters = $action->normalizeFilters($validated);

        try {
            $campusId = (int) session('current_campus_id');
            $filePath = $exportService->exportTeachingHoursToExcel($filters, $campusId);
            $fileName = 'teaching_hours_export_'.now()->format('Y-m-d').'.xlsx';

            Log::info('Teaching hours export completed successfully', [
                'user_id' => Auth::id(),
                'filters' => $filters,
                'file_name' => $fileName,
            ]);

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Teaching hours export failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'filters' => $filters,
            ]);

            return ApiResponse::error('Export failed: '.$e->getMessage(), status: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
