<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Academic;

use App\Actions\Academic\CheckGpaFinalizationEligibilityAction;
use App\Actions\Academic\FinalizeSemesterGpaAction;
use App\Actions\Academic\PreviewSemesterGpaAction;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Campus;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GpaManagementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Academic/Gpa/Index', [
            'semesters' => Semester::orderBy('start_date', 'desc')->get(),
            'campuses' => Campus::all(),
            'default_campus_id' => session('current_campus_id'),
        ]);
    }

    public function preview(Request $request, PreviewSemesterGpaAction $action): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'campus_id' => 'nullable|exists:campuses,id',
        ]);

        $campusId = $request->campus_id ?? session('current_campus_id');
        $data = $action->execute($request->semester_id, $campusId);

        return ApiResponse::success($data);
    }

    public function checkEligibility(Request $request, CheckGpaFinalizationEligibilityAction $action): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'campus_id' => 'nullable|exists:campuses,id',
        ]);

        $campusId = $request->campus_id ?? session('current_campus_id');
        $result = $action->execute($request->semester_id, $campusId);

        return ApiResponse::success($result);
    }

    public function finalize(Request $request, FinalizeSemesterGpaAction $action): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'campus_id' => 'nullable|exists:campuses,id',
        ]);

        // In a real app, authorized admin's lecture ID or user ID would be used.
        // For this project context, we'll assume the current user is an admin.
        $adminId = auth()->id(); 
        $campusId = $request->campus_id ?? session('current_campus_id');

        $result = $action->execute($request->semester_id, (int) $adminId, $campusId);

        return back()->with('success', $result['message']);
    }
}
