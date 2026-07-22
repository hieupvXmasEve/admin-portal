<?php

declare(strict_types=1);

namespace App\Modules\Upload\Http\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\UploadRecord;
use App\Models\User;
use App\Modules\Upload\Actions\DeleteUploadAction;
use App\Modules\Upload\Actions\StoreMultipleUploadsAction;
use App\Modules\Upload\Actions\StoreUploadAction;
use App\Modules\Upload\Http\Requests\Upload\ListUploadsRequest;
use App\Modules\Upload\Http\Requests\Upload\UploadMultipleRequest;
use App\Modules\Upload\Http\Requests\Upload\UploadRequest;
use App\Modules\Upload\Queries\GetUploadConfigurationQuery;
use App\Modules\Upload\Queries\GetUploadContextsQuery;
use App\Modules\Upload\Queries\ListUploadsQuery;
use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    /**
     * Image upload service.
     */
    protected UploadPlatform $uploadService;

    public function __construct(UploadPlatform $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Upload a single image file.
     */
    public function upload(UploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $context = $request->input('context');
            // Prepare metadata from request
            $metadata = array_filter([
                'alt_text' => $request->input('alt_text'),
                'description' => $request->input('description'),
                'expires_at' => $request->input('expires_at'),
            ]);

            // Upload the file
            $uploadRecord = StoreUploadAction::run(
                $this->uploadService,
                $file,
                $context,
                $this->actor(),
                $metadata,
            );

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'id' => $uploadRecord->id,
                    'filename' => $uploadRecord->filename,
                    'original_name' => $uploadRecord->original_name,
                    'mime_type' => $uploadRecord->mime_type,
                    'size' => $uploadRecord->size,
                    'context' => $uploadRecord->context,
                    'url' => $this->uploadService->getUrl($uploadRecord),
                    'metadata' => $uploadRecord->metadata,
                    'created_at' => $uploadRecord->created_at,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'context' => $request->input('context'),
                'filename' => $request->file('file')?->getClientOriginalName(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: '.$e->getMessage(),
                'errors' => [$e->getMessage()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Upload multiple image files.
     */
    public function uploadMultiple(UploadMultipleRequest $request): JsonResponse
    {
        try {
            $files = $request->file('files');
            $context = $request->input('context');

            // Prepare metadata from request
            $metadata = array_filter([
                'alt_text' => $request->input('alt_text'),
                'description' => $request->input('description'),
                'expires_at' => $request->input('expires_at'),
            ]);

            // Upload multiple files
            $result = StoreMultipleUploadsAction::run(
                $this->uploadService,
                $files,
                $context,
                $this->actor(),
                $metadata,
            );

            // Format successful uploads
            $successfulUploads = array_map(function ($uploadRecord) {
                return [
                    'id' => $uploadRecord->id,
                    'filename' => $uploadRecord->filename,
                    'original_name' => $uploadRecord->original_name,
                    'mime_type' => $uploadRecord->mime_type,
                    'size' => $uploadRecord->size,
                    'context' => $uploadRecord->context,
                    'url' => $this->uploadService->getUrl($uploadRecord),
                    'metadata' => $uploadRecord->metadata,
                    'created_at' => $uploadRecord->created_at,
                ];
            }, $result['successful']);

            $response = [
                'success' => true,
                'message' => count($result['successful']).' files uploaded successfully',
                'data' => [
                    'successful' => $successfulUploads,
                    'failed' => $result['failed'],
                    'summary' => [
                        'total' => count($files),
                        'successful' => count($result['successful']),
                        'failed' => count($result['failed']),
                    ],
                ],
            ];

            $statusCode = count($result['failed']) > 0 ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_CREATED;

            return response()->json($response, $statusCode);

        } catch (\Exception $e) {
            Log::error('Multiple image upload failed', [
                'context' => $request->input('context'),
                'file_count' => count($request->file('files', [])),
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Multiple upload failed: '.$e->getMessage(),
                'errors' => [$e->getMessage()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Get upload information by ID.
     */
    public function show(UploadRecord $uploadRecord): JsonResponse
    {
        try {
            $this->authorize('view', $uploadRecord);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $uploadRecord->id,
                    'filename' => $uploadRecord->filename,
                    'original_name' => $uploadRecord->original_name,
                    'mime_type' => $uploadRecord->mime_type,
                    'size' => $uploadRecord->size,
                    'context' => $uploadRecord->context,
                    'url' => $this->uploadService->getUrl($uploadRecord),
                    'metadata' => $uploadRecord->metadata,
                    'user_id' => $uploadRecord->user_id,
                    'created_at' => $uploadRecord->created_at,
                    'updated_at' => $uploadRecord->updated_at,
                ],
            ]);

        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied to this upload',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve upload information', [
                'upload_id' => $uploadRecord->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upload information',
                'errors' => [$e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete an uploaded file.
     */
    public function destroy(UploadRecord $uploadRecord): JsonResponse
    {
        try {
            $this->authorize('delete', $uploadRecord);
            $deleted = DeleteUploadAction::run($this->uploadService, $uploadRecord);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Upload deleted successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete upload',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied to delete this upload',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            Log::error('Failed to delete upload', [
                'upload_id' => $uploadRecord->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete upload',
                'errors' => [$e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user's uploads with pagination and filtering.
     */
    public function index(ListUploadsRequest $request, ListUploadsQuery $query): JsonResponse
    {
        try {
            $uploads = $query->handle($request->validated(), Auth::user());

            return response()->json([
                'success' => true,
                ...$uploads,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve uploads list', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve uploads',
                'errors' => [$e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Serve a private file with signature verification.
     */
    public function serve(Request $request, int $id): Response
    {
        try {
            // Verify using Laravel's signed URL validation
            if (! $request->hasValidSignature()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired signature',
                ], Response::HTTP_FORBIDDEN);
            }

            $uploadRecord = UploadRecord::findOrFail($id);

            // Check if file exists
            if (! Storage::disk($uploadRecord->disk)->exists($uploadRecord->path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Serve the file
            return Storage::disk($uploadRecord->disk)->response($uploadRecord->path, $uploadRecord->filename, [
                'Content-Type' => $uploadRecord->mime_type,
                'Cache-Control' => 'private, max-age=3600',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to serve file', [
                'upload_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to serve file',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get comprehensive upload configuration.
     */
    public function config(GetUploadConfigurationQuery $query): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $query->handle(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve upload configuration', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upload configuration',
                'errors' => [$e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get upload contexts and their configurations.
     */
    public function contexts(GetUploadContextsQuery $query): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $query->handle(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve upload contexts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upload contexts',
                'errors' => [$e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate a file before upload.
     */
    public function validateFile(UploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $context = $request->input('context');

            $validation = $this->uploadService->validateFileWithDetails($file, $context);

            return response()->json([
                'success' => $validation['valid'],
                'message' => $validation['valid'] ? 'File is valid for upload' : 'File validation failed',
                'data' => $validation,
            ], $validation['valid'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('File validation failed', [
                'context' => $request->input('context'),
                'filename' => $request->file('file')?->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File validation failed',
                'errors' => [$e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function actor(): UploadActor
    {
        $actor = Auth::user();

        return new UploadActor(
            userId: $actor instanceof User ? $actor->id : null,
            studentId: $actor instanceof Student ? $actor->id : null,
        );
    }
}
