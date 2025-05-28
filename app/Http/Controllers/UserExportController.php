<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\UserExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserExportController extends Controller
{
    public function __construct(
        private readonly UserExcelExportService $exportService
    ) {}

    public function exportExcel(Request $request): BinaryFileResponse|JsonResponse
    {
        // Validate request parameters
        $validated = $request->validate([
            'campus_id' => 'nullable|array',
            'campus_id.*' => 'exists:campuses,id',
            'role_id' => 'nullable|array',
            'role_id.*' => 'exists:roles,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
        ]);

        try {
            // Generate Excel file
            $filePath = $this->exportService->exportUsersToExcel($validated);

            // Generate download filename
            $downloadName = 'users_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

            // Return file download response
            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Excel export failed: ' . $e->getMessage(), [
                'filters' => $validated,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Export failed. Please try again later.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function exportExcelWithCurrentFilters(Request $request): BinaryFileResponse|JsonResponse
    {
        // Extract current filters from the request
        $filters = [];

        // Map Laravel query builder filters to our export filters
        if ($request->has('filter.name')) {
            $filters['search'] = $request->input('filter.name');
        }

        if ($request->has('filter.email')) {
            $filters['search'] = $request->input('filter.email');
        }

        if ($request->has('search')) {
            $filters['search'] = $request->input('search');
        }

        try {
            // Generate Excel file with current filters
            $filePath = $this->exportService->exportUsersToExcel($filters);

            // Generate download filename
            $downloadName = 'users_export_filtered_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

            // Return file download response
            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Excel export with filters failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Export failed. Please try again later.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
