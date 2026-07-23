<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Catalog\Actions\ExportUnitsToExcelAction;
use App\Modules\Academic\Catalog\Http\Requests\ExportUnitsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export Catalog units using the established spreadsheet template and filters.
 */
class UnitExportController extends Controller
{
    public function exportExcelWithCurrentFilters(
        ExportUnitsRequest $request,
        ExportUnitsToExcelAction $exportUnits,
    ): BinaryFileResponse|JsonResponse {
        $filters = $request->validated();

        try {
            $filePath = $exportUnits->handle($filters);

            return response()->download(
                $filePath,
                'units_export_filtered_'.now()->format('Y-m-d_H-i-s').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            report($exception);
            Log::error('Catalog unit export failed.', [
                'filters' => $filters,
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::serverError('Export failed. Please try again later.');
        }
    }
}
