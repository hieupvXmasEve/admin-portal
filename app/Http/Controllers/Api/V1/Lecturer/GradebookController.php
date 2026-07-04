<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\SaveGradebookScoresRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Modules\Academic\Actions\SaveLecturerGradebookScoresAction;
use App\Modules\Academic\Queries\GetLecturerCourseGradebookQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GradebookController extends Controller
{
    public function __construct(
        private readonly GetLecturerCourseGradebookQuery $getGradebook,
        private readonly SaveLecturerGradebookScoresAction $saveGradebookScores,
    ) {}

    public function show(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
            return ApiResponse::error('Unauthorized access to course offering', [], 403);
        }

        return ApiResponse::success(
            $this->getGradebook->handle($courseOffering),
            [],
            'Gradebook retrieved successfully'
        );
    }

    public function saveScores(SaveGradebookScoresRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
            return ApiResponse::error('Unauthorized access to course offering', [], 403);
        }

        try {
            return ApiResponse::success(
                $this->saveGradebookScores->handle($courseOffering, $request->validated('scores'), $lecturer->id),
                [],
                'Gradebook scores saved successfully'
            );
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }
    }

    private function canAccessCourseOffering(Lecture $lecturer, CourseOffering $courseOffering): bool
    {
        return $courseOffering->classSessions()
            ->where('lecture_id', $lecturer->id)
            ->exists();
    }
}
