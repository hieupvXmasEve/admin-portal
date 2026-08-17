<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Http\Requests\ExecuteBulkEgcPlacementImportRequest;
use App\Modules\Academic\Http\Requests\PreviewBulkEgcPlacementImportRequest;
use App\Modules\Academic\Progression\Actions\Placement\ImportBulkEgcPlacementFromCsvAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BulkEgcPlacementImportController extends Controller
{
    public function previewImport(
        PreviewBulkEgcPlacementImportRequest $request,
        ImportBulkEgcPlacementFromCsvAction $action
    ): JsonResponse {
        $result = $action->preview($request->file('file'));
        $previewToken = (string) Str::uuid();
        Cache::put(
            sprintf('bulk-egc-import-preview:%d:%s', $request->user()->id, $previewToken),
            ['file_hash' => hash_file('sha256', $request->file('file')->getRealPath())],
            now()->addMinutes(10)
        );

        return ApiResponse::success([...$result, 'preview_token' => $previewToken]);
    }

    public function executeImport(
        ExecuteBulkEgcPlacementImportRequest $request,
        ImportBulkEgcPlacementFromCsvAction $action
    ): JsonResponse {
        $previewToken = (string) $request->input('preview_token');
        $cacheKey = sprintf('bulk-egc-import-preview:%d:%s', $request->user()->id, $previewToken);
        $cachedPreview = Cache::get($cacheKey);
        $currentHash = hash_file('sha256', $request->file('file')->getRealPath());

        if (! $cachedPreview || ($cachedPreview['file_hash'] ?? null) !== $currentHash) {
            return ApiResponse::error('Preview token is invalid or file does not match preview.', status: 422);
        }

        $result = $action->execute($request->file('file'), (int) $request->user()->id);
        Cache::forget($cacheKey);

        return ApiResponse::success($result);
    }
}
