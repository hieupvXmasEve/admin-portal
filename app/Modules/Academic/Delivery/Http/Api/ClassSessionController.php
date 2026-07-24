<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSession\ClassSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\BulkDeleteClassSessionsAction;
use App\Modules\Academic\Delivery\Actions\CreateClassSessionAction;
use App\Modules\Academic\Delivery\Actions\DeleteClassSessionAction;
use App\Modules\Academic\Delivery\Actions\DeleteCourseOfferingClassSessionsAction;
use App\Modules\Academic\Delivery\Actions\GenerateClassSessionAttendanceAction;
use App\Modules\Academic\Delivery\Actions\GenerateCourseOfferingClassSessionsAction;
use App\Modules\Academic\Delivery\Queries\ListCourseOfferingClassSessionsQuery;
use App\Modules\Academic\Http\Requests\Delivery\BulkDeleteClassSessionsRequest;
use App\Modules\Academic\Http\Requests\Delivery\GenerateClassSessionsRequest;
use App\Modules\Academic\Http\Requests\Delivery\SaveClassSessionRequest;
use Illuminate\Http\JsonResponse;

final class ClassSessionController extends Controller
{
    public function index(CourseOffering $courseOffering, ListCourseOfferingClassSessionsQuery $query): JsonResponse
    {
        return ApiResponse::compatible($query->handle($courseOffering));
    }

    public function generate(GenerateClassSessionsRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        try {
            $sessions = GenerateCourseOfferingClassSessionsAction::run([
                ...$request->validated(),
                'course_offering_id' => $courseOffering->id,
            ]);

            return ApiResponse::compatible([
                'success' => true,
                'message' => 'Class sessions generated successfully',
                'data' => [
                    'sessions_count' => $sessions->count(),
                    'sessions' => ClassSessionResource::collection($sessions),
                ],
            ]);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible([
                'success' => false,
                'message' => $exception->getMessage(),
            ], str_contains($exception->getMessage(), 'already exist') ? 400 : 500);
        }
    }

    public function generateAttendance(ClassSession $classSession): JsonResponse
    {
        try {
            $result = GenerateClassSessionAttendanceAction::run(['class_session_id' => $classSession->id]);

            return ApiResponse::compatible($result, $result['success'] ? 200 : 400);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible([
                'success' => false,
                'message' => 'Failed to generate attendance: '.$exception->getMessage(),
            ], 500);
        }
    }

    public function destroy(CourseOffering $courseOffering): JsonResponse
    {
        try {
            $deleted = DeleteCourseOfferingClassSessionsAction::run(['course_offering_id' => $courseOffering->id]);

            return ApiResponse::compatible([
                'success' => true,
                'message' => $deleted ? 'Class sessions deleted successfully' : 'No class sessions found to delete',
            ]);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => 'Failed to delete class sessions: '.$exception->getMessage()], 500);
        }
    }

    public function store(SaveClassSessionRequest $request): JsonResponse
    {
        try {
            return ApiResponse::compatible([
                'success' => true,
                'message' => 'Class session created successfully',
                'data' => CreateClassSessionAction::run($request->validated()),
            ]);
        } catch (\DomainException $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => 'Failed to create class session: '.$exception->getMessage()], 500);
        }
    }

    public function destroySingle(ClassSession $classSession): JsonResponse
    {
        try {
            DeleteClassSessionAction::run(['class_session_id' => $classSession->id]);

            return ApiResponse::compatible(['success' => true, 'message' => 'Class session deleted successfully']);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => 'Failed to delete class session: '.$exception->getMessage()], 500);
        }
    }

    public function bulkDestroy(BulkDeleteClassSessionsRequest $request): JsonResponse
    {
        try {
            $deletedCount = BulkDeleteClassSessionsAction::run($request->validated());

            return ApiResponse::compatible(['success' => true, 'message' => $deletedCount.' class sessions deleted successfully']);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => 'Failed to delete class sessions: '.$exception->getMessage()], 500);
        }
    }
}
