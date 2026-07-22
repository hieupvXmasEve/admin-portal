<?php

declare(strict_types=1);

namespace App\Modules\Upload\Http\Api;

use App\Http\Controllers\Controller;
use App\Modules\Upload\Actions\StoreStudentAvatarAction;
use App\Modules\Upload\Http\Requests\Upload\StudentAvatarUploadRequest;
use App\Modules\Upload\Support\StudentAvatarTarget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StudentAvatarUploadController extends Controller
{
    /**
     * Upload a student avatar with student ID in filename.
     */
    public function upload(
        StudentAvatarUploadRequest $request,
        int $studentId,
        StoreStudentAvatarAction $storeStudentAvatar,
    ): JsonResponse {
        try {
            $this->authorize('upload', new StudentAvatarTarget($studentId));
            $file = $request->file('file');
            $userId = Auth::id();

            $uploadRecord = $storeStudentAvatar->handle($studentId, $file, $userId);

            Log::info('Student avatar uploaded successfully', [
                'upload_id' => $uploadRecord->id,
                'student_id' => $studentId,
                'filename' => $uploadRecord->filename,
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
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied to update this student avatar',
            ], Response::HTTP_FORBIDDEN);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ModelNotFoundException $e) {
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
                'message' => 'Upload failed: '.$e->getMessage(),
                'errors' => [$e->getMessage()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
