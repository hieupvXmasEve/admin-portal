<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetSemesterFilterOptionsQuery;
use App\Modules\Academic\Http\Requests\ListCourseRankingRequest;
use App\Modules\Academic\Progression\Queries\GetCourseRankingQuery;
use Inertia\Inertia;
use Inertia\Response;

class CourseRankingController extends Controller
{
    /**
     * Display the course ranking page.
     */
    public function index(
        ListCourseRankingRequest $request,
        GetSemesterFilterOptionsQuery $semesterFilterOptions,
        GetCourseRankingQuery $courseRanking,
    ): Response {
        $campusId = session('current_campus_id');
        $semesterOptions = $semesterFilterOptions->handle();

        $validated = $request->validated();
        $semesterId = $validated['semester_id'] ?? $semesterOptions['active_semester_id'];

        $rankingData = null;
        if ($semesterId && $campusId) {
            $rankingData = $courseRanking->handle((int) $semesterId, (int) $campusId);
        }

        return Inertia::render('Academic/CourseRanking/Index', [
            'ranking' => $rankingData,
            'filters' => [
                'active' => [
                    'semester_id' => $semesterId,
                ],
                'options' => [
                    'semesters' => $semesterOptions['semesters'],
                ],
            ],
        ]);
    }
}
