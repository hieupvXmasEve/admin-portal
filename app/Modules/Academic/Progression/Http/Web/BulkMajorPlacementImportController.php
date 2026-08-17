<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Http\Requests\ExecuteBulkMajorPlacementImportRequest;
use App\Modules\Academic\Http\Requests\PreviewBulkMajorPlacementImportRequest;
use App\Modules\Academic\Progression\Actions\Placement\ImportBulkMajorPlacementFromCsvAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BulkMajorPlacementImportController extends Controller
{
    public function previewImport(
        PreviewBulkMajorPlacementImportRequest $request,
        ImportBulkMajorPlacementFromCsvAction $action
    ): JsonResponse {
        $result = $action->preview($request->file('file'));
        $previewToken = (string) Str::uuid();
        $validStudentIds = collect($result['rows'])
            ->where('status', 'valid')
            ->pluck('student.id')
            ->all();
        Cache::put(
            sprintf('bulk-major-import-preview:%d:%s', $request->user()->id, $previewToken),
            [
                'file_hash' => hash_file('sha256', $request->file('file')->getRealPath()),
                'valid_student_ids' => $validStudentIds,
            ],
            now()->addMinutes(10)
        );

        return ApiResponse::success([...$result, 'preview_token' => $previewToken]);
    }

    public function executeImport(
        ExecuteBulkMajorPlacementImportRequest $request,
        ImportBulkMajorPlacementFromCsvAction $action
    ): JsonResponse {
        $previewToken = (string) $request->input('preview_token');
        $cacheKey = sprintf('bulk-major-import-preview:%d:%s', $request->user()->id, $previewToken);
        $cachedPreview = Cache::get($cacheKey);
        $currentHash = hash_file('sha256', $request->file('file')->getRealPath());

        if (! $cachedPreview || ($cachedPreview['file_hash'] ?? null) !== $currentHash) {
            return ApiResponse::error('Preview token is invalid or file does not match preview.', status: 422);
        }

        $result = $action->execute($request->file('file'), (int) $request->user()->id, $cachedPreview['valid_student_ids'] ?? []);
        Cache::forget($cacheKey);

        return ApiResponse::success($result);
    }
}
