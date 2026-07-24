<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\SaveGradebookScoresRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Modules\Academic\Delivery\Actions\SaveLecturerGradebookScoresAction;
use App\Modules\Academic\Delivery\Queries\GetLecturerCourseGradebookQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GradebookController extends Controller
{
    public function __construct(
        private readonly GetLecturerCourseGradebookQuery $getGradebook,
    ) {}

    public function show(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        if (Gate::forUser($lecturer)->denies('viewGradebook', $courseOffering)) {
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

        if (Gate::forUser($lecturer)->denies('viewGradebook', $courseOffering)) {
            return ApiResponse::error('Unauthorized access to course offering', [], 403);
        }

        try {
            return ApiResponse::success(
                SaveLecturerGradebookScoresAction::run([
                    'course_offering' => $courseOffering,
                    'scores' => $request->gradebookScores($courseOffering),
                    'lecturer_id' => $lecturer->id,
                ]),
                [],
                'Gradebook scores saved successfully'
            );
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }
    }
}
