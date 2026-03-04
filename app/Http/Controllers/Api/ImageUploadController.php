<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadRequest;
use App\Models\UploadRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ImageUploadController extends Controller
{
    /**
     * Image upload service.
     */
    protected ImageUploadService $uploadService;

    public function __construct(ImageUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Upload a single image file.
     */
    public function upload(ImageUploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $context = $request->input('context');
            $authUser = Auth::user();
            $userId = $authUser instanceof User ? $authUser->id : null;

            // Prepare metadata from request
            $metadata = array_filter([
                'alt_text' => $request->input('alt_text'),
                'description' => $request->input('description'),
                'expires_at' => $request->input('expires_at'),
            ]);

            // Upload the file
            $uploadRecord = $this->uploadService->upload(
                $file,
                $context,
                $userId,
                $authUser instanceof Student ? $authUser->id : null,
                $metadata
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
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Upload multiple image files.
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        try {
            // Validate basic requirements
            $request->validate([
                'files' => 'required|array|min:1|max:10',
                'files.*' => 'required|file',
                'context' => 'required|string|in:' . implode(',', array_keys(config('uploads.contexts'))),
            ]);

            $files = $request->file('files');
            $context = $request->input('context');
            $authUser = Auth::user();
            $userId = $authUser instanceof User ? $authUser->id : null;

            // Prepare metadata from request
            $metadata = array_filter([
                'alt_text' => $request->input('alt_text'),
                'description' => $request->input('description'),
                'expires_at' => $request->input('expires_at'),
            ]);

            // Upload multiple files
            $result = $this->uploadService->uploadMultiple(
                $files,
                $context,
                $userId,
                $authUser instanceof Student ? $authUser->id : null,
                $metadata
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
                'message' => count($result['successful']) . ' files uploaded successfully',
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
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Multiple upload failed: ' . $e->getMessage(),
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
            // Check if user has access to this upload
            if (!$this->canAccessUpload($uploadRecord)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this upload',
                ], Response::HTTP_FORBIDDEN);
            }

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
            // Check if user has access to delete this upload
            if (!$this->canDeleteUpload($uploadRecord)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to delete this upload',
                ], Response::HTTP_FORBIDDEN);
            }

            $deleted = $this->uploadService->delete($uploadRecord);

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
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'context' => 'nullable|string|in:' . implode(',', array_keys(config('uploads.contexts'))),
                'per_page' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|max:255',
            ]);

            $query = UploadRecord::query();

            // Filter by user if not admin
            if (!Auth::user()?->hasRole('admin')) {
                $query->where('user_id', Auth::id());
            }

            // Filter by context
            if ($request->filled('context')) {
                $query->where('context', $request->input('context'));
            }

            // Search by filename or original name
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('filename', 'like', "%{$search}%")
                      ->orWhere('original_name', 'like', "%{$search}%");
                });
            }

            // Order by creation date (newest first)
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $uploads = $query->paginate($perPage);

            // Transform the data
            $uploads->getCollection()->transform(function ($uploadRecord) {
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
            });

            return response()->json([
                'success' => true,
                'data' => $uploads->items(),
                'meta' => [
                    'current_page' => $uploads->currentPage(),
                    'last_page' => $uploads->lastPage(),
                    'per_page' => $uploads->perPage(),
                    'total' => $uploads->total(),
                    'from' => $uploads->firstItem(),
                    'to' => $uploads->lastItem(),
                ],
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
            if (!$request->hasValidSignature()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired signature',
                ], Response::HTTP_FORBIDDEN);
            }

            $uploadRecord = UploadRecord::findOrFail($id);

            // Check if file exists
            if (!Storage::disk($uploadRecord->disk)->exists($uploadRecord->path)) {
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
    public function config(): JsonResponse
    {
        try {
            $contexts = [];

            foreach ($this->uploadService->getAvailableContexts() as $context) {
                $config = $this->uploadService->getContextConfiguration($context);

                $contexts[$context] = [
                    'max_size' => $config['max_size'],
                    'allowed_types' => $config['allowed_types'],
                    'allowed_extensions' => $config['allowed_extensions'] ?? [],
                    'directory' => $config['directory'],
                    'generate_thumbnails' => $config['generate_thumbnails'] ?? false,
                    'public' => $config['public'] ?? true,
                    'disk' => $config['disk'] ?? 'images',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'contexts' => $contexts,
                    'defaults' => config('uploads.defaults'),
                    'security' => config('uploads.security'),
                    'performance' => config('uploads.performance'),
                ],
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
    public function contexts(): JsonResponse
    {
        try {
            $contexts = [];

            foreach ($this->uploadService->getAvailableContexts() as $context) {
                $config = $this->uploadService->getContextConfiguration($context);

                $contexts[$context] = [
                    'max_size' => $config['max_size'],
                    'max_size_mb' => round($config['max_size'] / 1024, 2),
                    'allowed_types' => $config['allowed_types'],
                    'allowed_extensions' => $config['allowed_extensions'] ?? [],
                    'public' => $config['public'] ?? true,
                    'directory' => $config['directory'],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $contexts,
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
    public function validateFile(ImageUploadRequest $request): JsonResponse
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

    /**
     * Check if the current user can access the upload.
     */
    protected function canAccessUpload(UploadRecord $uploadRecord): bool
    {
        $user = Auth::user();

        // Admin can access all uploads
        if ($user?->hasRole('admin')) {
            return true;
        }

        // Users can access their own uploads
        if ($uploadRecord->user_id === Auth::id()) {
            return true;
        }

        // Check if upload is in a public context
        return $this->uploadService->isContextPublic($uploadRecord->context);
    }

    /**
     * Check if the current user can delete the upload.
     */
    protected function canDeleteUpload(UploadRecord $uploadRecord): bool
    {
        $user = Auth::user();

        // Admin can delete all uploads
        if ($user?->hasRole('admin')) {
            return true;
        }

        // Users can only delete their own uploads
        return $uploadRecord->user_id === Auth::id();
    }
}
