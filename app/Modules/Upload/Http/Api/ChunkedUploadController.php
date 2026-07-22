<?php

declare(strict_types=1);

namespace App\Modules\Upload\Http\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Modules\Upload\Actions\CancelChunkedUploadAction;
use App\Modules\Upload\Actions\InitializeChunkedUploadAction;
use App\Modules\Upload\Actions\StoreUploadChunkAction;
use App\Modules\Upload\Http\Requests\Upload\InitializeChunkedUploadRequest;
use App\Modules\Upload\Http\Requests\Upload\UploadChunkRequest;
use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChunkedUploadController extends Controller
{
    /**
     * Chunked upload service.
     */
    protected UploadPlatform $chunkedUploadService;

    public function __construct(UploadPlatform $chunkedUploadService)
    {
        $this->chunkedUploadService = $chunkedUploadService;
    }

    /**
     * Initialize a chunked upload session.
     */
    public function initialize(InitializeChunkedUploadRequest $request): JsonResponse
    {
        try {
            $result = InitializeChunkedUploadAction::run(
                $this->chunkedUploadService,
                $request->validated(),
                $this->actor(),
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initialize chunked upload', [
                'filename' => $request->input('filename'),
                'file_size' => $request->input('file_size'),
                'context' => $request->input('context'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload a file chunk.
     */
    public function uploadChunk(UploadChunkRequest $request): JsonResponse
    {
        try {
            $result = StoreUploadChunkAction::run($this->chunkedUploadService, $request->validated(), $this->actor());

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            Log::error('Failed to upload chunk', [
                'upload_id' => $request->input('upload_id'),
                'chunk_index' => $request->input('chunk_index'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get upload session status.
     */
    public function status(Request $request, string $uploadId): JsonResponse
    {
        try {
            $status = $this->chunkedUploadService->getUploadStatus($uploadId, $this->actor());

            if (isset($status['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $status['error'],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            Log::error('Failed to get upload status', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get upload status',
            ], 500);
        }
    }

    /**
     * Cancel chunked upload.
     */
    public function cancel(Request $request, string $uploadId): JsonResponse
    {
        try {
            $result = CancelChunkedUploadAction::run($this->chunkedUploadService, $uploadId, $this->actor());

            if (! $result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload session not found or already completed',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Upload cancelled successfully',
            ]);

        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            Log::error('Failed to cancel upload', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel upload',
            ], 500);
        }
    }

    /**
     * Get chunked upload statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->chunkedUploadService->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get chunked upload statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
            ], 500);
        }
    }

    private function actor(): UploadActor
    {
        $actor = auth()->user();

        return new UploadActor(
            userId: $actor instanceof User ? $actor->id : null,
            studentId: $actor instanceof Student ? $actor->id : null,
        );
    }
}
