<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Catalog\Actions\GenerateUnitImportTemplateAction;
use App\Modules\Academic\Catalog\Actions\PreviewUnitImportAction;
use App\Modules\Academic\Catalog\Actions\ProcessUnitImportAction;
use App\Modules\Academic\Catalog\Actions\UploadUnitImportFileAction;
use App\Modules\Academic\Catalog\Http\Requests\PreviewUnitImportRequest;
use App\Modules\Academic\Catalog\Http\Requests\ProcessUnitImportRequest;
use App\Modules\Academic\Catalog\Http\Requests\UploadUnitImportRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Catalog owns the Unit import workflow and generated workbook templates.
 */
class UnitImportController extends Controller
{
    public function showImportForm(): Response
    {
        return Inertia::render('Units/Import', [
            'maxFileSize' => config('import.max_file_size', '10MB'),
            'allowedExtensions' => config('import.allowed_extensions', ['xlsx', 'xls']),
            'availableFormats' => [
                'simple' => 'Simple Format (Units only)',
                'detailed' => 'Detailed Format (Units with Prerequisites)',
                'complete' => 'Complete Format (Units with all relationships)',
                'combined' => 'Combined Format (Units and Syllabus)',
            ],
        ]);
    }

    public function uploadFile(UploadUnitImportRequest $request, UploadUnitImportFileAction $uploadImport): JsonResponse
    {
        try {
            $result = $uploadImport->handle($request->file('file'));

            return ApiResponse::success($result);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error($exception->getMessage(), status: 400);
        }
    }

    public function previewImport(PreviewUnitImportRequest $request, PreviewUnitImportAction $previewImport): JsonResponse
    {
        $validated = $request->validated();

        try {
            $preview = $previewImport->handle($validated['file_path'], $validated['preview_rows'] ?? 10);

            return ApiResponse::success(['preview' => $preview]);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::error($exception->getMessage(), status: 400);
        }
    }

    public function processImport(ProcessUnitImportRequest $request, ProcessUnitImportAction $processImport): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $processImport->handle(
                $validated['file_path'],
                [
                    'duplicate_handling' => $validated['duplicate_handling'] ?? 'update',
                    'create_prerequisites' => $validated['create_prerequisites'] ?? false,
                    'create_equivalents' => $validated['create_equivalents'] ?? false,
                ],
                auth()->id(),
            );

            return ApiResponse::success(['result' => $result]);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Unit import failed: '.$exception->getMessage());
        }
    }

    public function getImportHistory(): JsonResponse
    {
        return ApiResponse::success(['history' => []]);
    }

    public function downloadTemplate(string $format): BinaryFileResponse
    {
        $filename = match ($format) {
            'simple' => 'units_simple_template.xlsx',
            'detailed' => 'units_detailed_template.xlsx',
            'complete' => 'units_complete_template.xlsx',
            'combined' => 'units_syllabus_combined_template.xlsx',
            default => abort(404, 'Template not found'),
        };
        $path = app(GenerateUnitImportTemplateAction::class)->handle($format);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
