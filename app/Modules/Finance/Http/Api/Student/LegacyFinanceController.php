<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Shared\Contracts\Finance\StudentPortalFinanceSummaryReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class LegacyFinanceController extends Controller
{
    public function index(Request $request, StudentPortalFinanceSummaryReader $reader): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        try {
            return ApiResponse::success($reader->handle((int) $student->id));
        } catch (\Throwable $e) {
            Log::error('Finance index error', [
                'error' => $e->getMessage(),
                'student_id' => $student->id,
            ]);

            return ApiResponse::serverError('Failed to retrieve finance summary');
        }
    }

    public function semester(Request $request, int $semesterId, StudentPortalFinanceSummaryReader $reader): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        try {
            $finance = $reader->semesterFor((int) $student->id, $semesterId);
            if ($finance === null) {
                return ApiResponse::notFound('Semester not found');
            }

            return ApiResponse::success($finance);
        } catch (\Throwable $e) {
            Log::error('Finance semester error', [
                'error' => $e->getMessage(),
                'student_id' => $student->id,
            ]);

            return ApiResponse::serverError('Failed to retrieve finance details');
        }
    }
}
