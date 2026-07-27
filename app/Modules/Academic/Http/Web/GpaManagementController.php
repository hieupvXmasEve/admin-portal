<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Actions\Academic\CheckGpaFinalizationEligibilityAction;
use App\Actions\Academic\FinalizeSemesterGpaAction;
use App\Actions\Academic\PreviewSemesterGpaAction;
use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetSemesterFilterOptionsQuery;
use App\Modules\Academic\Http\Requests\Gpa\FinalizeGpaRequest;
use App\Modules\Academic\Http\Requests\Gpa\PreviewGpaFinalizationRequest;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GpaManagementController extends Controller
{
    public function index(
        PreviewGpaFinalizationRequest $request,
        PreviewSemesterGpaAction $previewAction,
        CheckGpaFinalizationEligibilityAction $eligibilityAction,
        GetSemesterFilterOptionsQuery $semesterFilterOptions,
        CampusReferenceReader $campuses,
    ): Response {
        $validated = $request->validated();

        $semesterId = $validated['semester_id'] ?? null;
        $campusId = $validated['campus_id'] ?? session('current_campus_id');

        $previewData = [];
        $eligibilityResult = null;

        if ($semesterId) {
            $previewData = $previewAction->execute((int) $semesterId, (int) $campusId);
            $eligibilityResult = $eligibilityAction->execute((int) $semesterId, (int) $campusId);
        }

        return Inertia::render('Admin/Academic/Gpa/Index', [
            'semesters' => $semesterFilterOptions->handle()['semesters'],
            'campuses' => collect($campuses->all())
                ->map(static fn ($campus): array => ['id' => $campus->id, 'name' => $campus->name])
                ->values()
                ->all(),
            'default_campus_id' => session('current_campus_id'),
            'filters' => [
                'semester_id' => $semesterId,
                'campus_id' => $campusId,
            ],
            'previewData' => $previewData,
            'eligibilityResult' => $eligibilityResult,
        ]);
    }

    public function finalize(FinalizeGpaRequest $request, FinalizeSemesterGpaAction $action): RedirectResponse
    {
        $validated = $request->validated();

        // In a real app, authorized admin's lecture ID or user ID would be used.
        // For this project context, we'll assume the current user is an admin.
        $adminId = auth()->id();
        $campusId = $validated['campus_id'] ?? session('current_campus_id');

        $result = $action->execute($validated['semester_id'], (int) $adminId, (int) $campusId);

        if (($result['success'] ?? true) === false) {
            return back()->withErrors(['finalize' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}
