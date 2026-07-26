<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Shared\Contracts\StudentRegistry\StudentPortalContextReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentContextController extends Controller
{
    public function index(Request $request, StudentPortalContextReader $contexts): JsonResponse
    {
        $context = $contexts->forStudent((int) $request->user()?->getAuthIdentifier());

        return ApiResponse::success($context);
    }
}
