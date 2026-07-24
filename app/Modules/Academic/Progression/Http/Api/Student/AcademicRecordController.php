<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Shared\Contracts\Academic\StudentAcademicRecordsReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcademicRecordController extends Controller
{
    public function index(Request $request, StudentAcademicRecordsReader $reader): JsonResponse
    {
        return ApiResponse::compatible($reader->forStudent((int) $request->user()->getKey())->toArray());
    }
}
