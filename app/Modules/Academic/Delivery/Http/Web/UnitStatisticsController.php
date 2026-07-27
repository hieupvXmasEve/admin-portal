<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetSemesterFilterOptionsQuery;
use App\Modules\Academic\Delivery\Actions\GetUnitStatisticsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitStatisticsController extends Controller
{
    public function __construct(
        private readonly GetUnitStatisticsAction $getUnitStatisticsAction,
        private readonly GetSemesterFilterOptionsQuery $semesterFilterOptions,
    ) {}

    /**
     * Display statistics for a specific unit.
     */
    public function show(Request $request, int $unitId): Response
    {
        $semesterOptions = $this->semesterFilterOptions->handle();

        $semesterId = $request->input('semester_id') ?: $semesterOptions['active_semester_id'];
        $offeringId = $request->input('course_offering_id');

        $filters = [
            'semester_id' => $semesterId,
            'course_offering_id' => $offeringId,
        ];

        $data = $this->getUnitStatisticsAction->execute($unitId, $filters);

        $semesters = $semesterOptions['semesters'];

        return Inertia::render('CourseStatistics/UnitDetail', [
            'data' => $data,
            'semesters' => $semesters,
            'filters' => $filters,
        ]);
    }
}
