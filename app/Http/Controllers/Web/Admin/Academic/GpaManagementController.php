<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Academic;

use App\Actions\Academic\CheckGpaFinalizationEligibilityAction;
use App\Actions\Academic\FinalizeSemesterGpaAction;
use App\Actions\Academic\PreviewSemesterGpaAction;
use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GpaManagementController extends Controller
{
    public function index(
        Request $request,
        PreviewSemesterGpaAction $previewAction,
        CheckGpaFinalizationEligibilityAction $eligibilityAction
    ): Response {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'campus_id' => 'nullable|integer|exists:campuses,id',
        ]);

        $semesterId = $validated['semester_id'] ?? null;
        $campusId = $validated['campus_id'] ?? session('current_campus_id');

        $previewData = [];
        $eligibilityResult = null;

        if ($semesterId) {
            $previewData = $previewAction->execute((int) $semesterId, (int) $campusId);
            $eligibilityResult = $eligibilityAction->execute((int) $semesterId, (int) $campusId);
        }

        return Inertia::render('Admin/Academic/Gpa/Index', [
            'semesters' => Semester::orderBy('start_date', 'desc')->get(),
            'campuses' => Campus::all(),
            'default_campus_id' => session('current_campus_id'),
            'filters' => [
                'semester_id' => $semesterId,
                'campus_id' => $campusId,
            ],
            'previewData' => $previewData,
            'eligibilityResult' => $eligibilityResult,
        ]);
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

        $result = $action->execute($request->semester_id, (int) $adminId, (int) $campusId);

        return back()->with('success', $result['message']);
    }
}
