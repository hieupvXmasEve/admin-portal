<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Academic\GetStudentAcademicRecordsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicRecordController extends Controller
{
    /**
     * Get student academic records (GPA summary & history).
     */
    public function index(Request $request, GetStudentAcademicRecordsAction $action): JsonResponse
    {
        // For Student Portal API, we usually identify the student via the authenticated user.
        // Assuming the authenticated user has a 'student' relationship.
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $data = $action->execute($user->student);

        return response()->json($data);
    }
}
