<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Progression\Queries\GetPerformanceDashboardQuery;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceDashboardController extends Controller
{
    public function __construct(
        private readonly CampusReferenceReader $campuses,
        private readonly GetSemesterReferenceOptionsQuery $semesters,
    ) {}

    public function index(Request $request, GetPerformanceDashboardQuery $query, AcademicPeriodReader $academicPeriods): Response
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ]);

        $currentCampusId = session('current_campus_id');
        $campusId = isset($validated['campus_id']) ? (int) $validated['campus_id'] : (int) $currentCampusId;

        // Default to latest active semester or just latest semester if none active
        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : ($academicPeriods->current()?->id ?? $this->semesters->latestId());

        $data = $query->handle($campusId, $semesterId);

        return Inertia::render('Admin/Academic/Performance/Dashboard', [
            'stats' => $data,
            'filters' => [
                'campus_id' => $campusId,
                'semester_id' => $semesterId,
            ],
            'options' => [
                'campuses' => array_map(
                    static fn (CampusReference $campus): array => $campus->toArray(),
                    $this->campuses->all(),
                ),
                'semesters' => $this->semesters->records(),
            ],
        ]);
    }
}
