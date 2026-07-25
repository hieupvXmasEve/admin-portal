<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exports\AcademicReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\GetAcademicReportRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ExcelExportService;
use App\Shared\Contracts\Academic\AcademicReportReader;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AcademicReportController extends Controller
{
    public function __construct(
        protected AcademicReportReader $academicReportReader,
        protected ExcelExportService $excelExportService
    ) {}

    /**
     * Get academic report data or export.
     */
    public function index(GetAcademicReportRequest $request): JsonResponse|BinaryFileResponse
    {
        $data = $this->academicReportReader->handle($request->validated());

        if ($request->has('export')) {
            $format = $request->input('export', 'xlsx');
            $filename = $this->excelExportService->generateFilenameWithTimestamp('academic_report', $format);

            return $this->excelExportService->download(
                new AcademicReportExport($data),
                $filename,
                $format
            );
        }

        return ApiResponse::success($data);
    }
}
