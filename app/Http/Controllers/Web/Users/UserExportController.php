<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;
use App\Services\UserExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserExportController extends Controller
{
    /**
     * Export users to Excel
     */
    public function exportExcel(): BinaryFileResponse|JsonResponse
    {
        try {
            $exportService = new UserExcelExportService;
            $filePath = $exportService->exportUsersToExcel();
            $fileName = 'users_export_'.now()->format('Y-m-d').'.xlsx';

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export users to Excel with current filters
     */
    public function exportExcelWithCurrentFilters(Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            // Build filters from request
            $filters = [];

            // Handle search filter
            if ($request->filled('search')) {
                $filters['search'] = $request->get('search');
            }

            // Handle column filters
            if ($request->has('filter')) {
                $requestFilters = $request->get('filter');
                if (is_array($requestFilters)) {
                    if (! empty($requestFilters['name'])) {
                        $filters['name'] = $requestFilters['name'];
                    }
                    if (! empty($requestFilters['email'])) {
                        $filters['email'] = $requestFilters['email'];
                    }
                }
            }

            $exportService = new UserExcelExportService($filters);
            $filePath = $exportService->exportUsersToExcel($filters);
            $fileName = 'users_export_'.now()->format('Y-m-d').'.xlsx';

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
