<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Queries\GetStudentContextQuery;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentContextController extends Controller
{
    public function index(Request $request, GetStudentContextQuery $query): JsonResponse
    {
        $student = $request->user();
        $context = $query->handle($student);

        return ApiResponse::success($context);
    }
}
