<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StudentAvatarController extends Controller
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
     * Upload a student avatar with student ID in filename.
     */
    public function upload(Request $request, int $studentId): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'file' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:2048', // 2MB max
            ]);

            // Check if student exists and user has permission
            $student = Student::findOrFail($studentId);

            $file = $request->file('file');
            $context = 'avatar';
            $userId = Auth::id();

            // Create a custom filename generator that includes student ID
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $timestamp = now()->format('Y-m-d_H-i-s');
            $customFilename = "student_{$student->student_id}_{$timestamp}.{$extension}";

            // Prepare metadata
            $metadata = [
                'student_id' => $student->student_id,
                'description' => 'Student profile avatar',
                'uploaded_by' => $userId,
            ];

            // Get context configuration
            $config = $this->uploadService->getContextConfiguration($context);

            // Generate custom path with student ID
            $directory = $config['directory'] ?? 'avatars';
            $dateDirectory = now()->format('Y/m');
            $customPath = "{$directory}/{$dateDirectory}/{$customFilename}";

            // Store the file with custom filename
            $disk = $config['disk'];
            $storedPath = $file->storeAs(
                dirname($customPath),
                basename($customPath),
                $disk
            );

            if (!$storedPath) {
                throw new \RuntimeException('Failed to store uploaded file');
            }

            // Generate URL
            $url = $this->uploadService->generatePublicUrl($storedPath, $disk);

            // Create upload record with custom filename
            $uploadRecord = \App\Models\UploadRecord::create([
                'filename' => $customFilename,
                'original_name' => $originalName,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'context' => $context,
                'path' => $storedPath,
                'disk' => $disk,
                'url' => $url,
                'hash' => hash_file('sha256', $file->getRealPath()),
                'user_id' => $userId,
                'student_id' => $student->id,
                'metadata' => $metadata,
            ]);

            Log::info('Student avatar uploaded successfully', [
                'upload_id' => $uploadRecord->id,
                'student_id' => $studentId,
                'filename' => $customFilename,
                'size' => $file->getSize(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'id' => $uploadRecord->id,
                    'filename' => $uploadRecord->filename,
                    'original_name' => $uploadRecord->original_name,
                    'mime_type' => $uploadRecord->mime_type,
                    'size' => $uploadRecord->size,
                    'context' => $uploadRecord->context,
                    'url' => $uploadRecord->url,
                    'metadata' => $uploadRecord->metadata,
                    'created_at' => $uploadRecord->created_at,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Student avatar upload failed', [
                'student_id' => $studentId,
                'filename' => $request->file('file')?->getClientOriginalName(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
