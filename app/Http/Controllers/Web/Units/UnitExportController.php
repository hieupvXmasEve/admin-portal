<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Units;

use App\Http\Controllers\Controller;
use App\Services\UnitExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UnitExportController extends Controller
{
    public function __construct(
        private readonly UnitExcelExportService $exportService
    ) {}

    public function exportExcel(Request $request): BinaryFileResponse|JsonResponse
    {
        // Validate request parameters
        $validated = $request->validate([
            'credit_points_from' => 'nullable|numeric|min:0',
            'credit_points_to' => 'nullable|numeric|min:0|gte:credit_points_from',
            'has_prerequisites' => 'nullable|boolean',
            'has_equivalents' => 'nullable|boolean',
            'in_curricula' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
        ]);

        try {
            // Generate Excel file
            $filePath = $this->exportService->exportUnitsToExcel($validated);

            // Generate download filename
            $downloadName = 'units_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

            // Return file download response
            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Units Excel export failed: ' . $e->getMessage(), [
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

        // Map query parameters to export filters
        if ($request->has('search')) {
            $filters['search'] = $request->input('search');
        }

        if ($request->has('credit_points_from')) {
            $filters['credit_points_from'] = $request->input('credit_points_from');
        }

        if ($request->has('credit_points_to')) {
            $filters['credit_points_to'] = $request->input('credit_points_to');
        }

        try {
            // Generate Excel file with current filters
            $filePath = $this->exportService->exportUnitsToExcel($filters);

            // Generate download filename
            $downloadName = 'units_export_filtered_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

            // Return file download response
            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Units Excel export with filters failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Export failed. Please try again later.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
