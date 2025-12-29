<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Unit\GetUnitStatisticsAction;
use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitStatisticsController extends Controller
{
    public function __construct(
        private readonly GetUnitStatisticsAction $getUnitStatisticsAction
    ) {}

    /**
     * Display statistics for a specific unit.
     */
    public function show(Request $request, int $unitId): Response
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            $activeSemester = Semester::getActiveSemester();
            if ($activeSemester) {
                $semesterId = $activeSemester->id;
            }
        }

        $filters = ['semester_id' => $semesterId];

        $data = $this->getUnitStatisticsAction->execute($unitId, $filters);

        $semesters = Semester::where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code']);

        return Inertia::render('CourseStatistics/UnitDetail', [
            'data' => $data,
            'semesters' => $semesters,
            'filters' => $filters,
        ]);
    }
}
