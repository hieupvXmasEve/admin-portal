<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Http\Requests\Progression\StudentGpaTrendRequest;
use App\Shared\Contracts\Academic\StudentGpaTrendReader;
use Illuminate\Http\JsonResponse;

final class GpaTrendController extends Controller
{
    public function index(StudentGpaTrendRequest $request, StudentGpaTrendReader $reader): JsonResponse
    {
        try {
            return ApiResponse::success(
                $reader->forStudent((int) $request->user()->getKey(), $request->semesterCount())->toArray(),
                [],
                'GPA trend retrieved successfully',
            );
        } catch (\Throwable) {
            return ApiResponse::serverError('Failed to retrieve GPA trend');
        }
    }
}
