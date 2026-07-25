<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\AcademicReportReader;
use App\Shared\Contracts\Academic\ProgramReferenceReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicReportController extends Controller
{
    /**
     * Display the academic report page.
     */
    public function index(
        Request $request,
        AcademicReportReader $reportReader,
        AcademicPeriodReader $academicPeriods,
        CampusReferenceReader $campuses,
        ProgramReferenceReader $programs,
    ): Response {
        $activeSemester = $academicPeriods->active();
        $currentCampusId = session('current_campus_id');

        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Set defaults if not provided in request
        $filters = array_merge([
            'semester_id' => $activeSemester?->id,
            'campus_id' => $currentCampusId,
            'per_page' => 15,
        ], array_filter($validated));

        $report = null;
        if (! empty($filters['semester_id'])) {
            $report = $reportReader->handle($filters);
        }

        return Inertia::render('Academic/Report/Index', [
            'report' => $report,
            'filters' => [
                'active' => [
                    'campus_id' => isset($filters['campus_id']) ? (int) $filters['campus_id'] : null,
                    'semester_id' => isset($filters['semester_id']) ? (int) $filters['semester_id'] : null,
                    'program_id' => isset($filters['program_id']) ? (int) $filters['program_id'] : null,
                    'keyword' => $filters['keyword'] ?? '',
                    'per_page' => (int) ($filters['per_page'] ?? 15),
                ],
                'options' => [
                    'campuses' => collect($campuses->all())
                        ->map(static fn ($campus): array => ['id' => $campus->id, 'name' => $campus->name])
                        ->values()
                        ->all(),
                    'semesters' => collect($academicPeriods->selectable())
                        ->map(static fn ($semester): array => ['id' => $semester->id, 'name' => $semester->name])
                        ->values()
                        ->all(),
                    'programs' => collect($programs->all())
                        ->map(static fn (array $program): array => ['id' => $program['id'], 'name' => $program['name']])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }
}
