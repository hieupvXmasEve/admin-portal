<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetProgramReferenceOptionsQuery;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Progression\Exports\GpaHistoryExport;
use App\Modules\Academic\Progression\Queries\ListGpaHistoryQuery;
use App\Services\ExcelExportService;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GpaHistoryController extends Controller
{
    public function __construct(
        private readonly CampusReferenceReader $campuses,
        private readonly GetSemesterReferenceOptionsQuery $semesters,
        private readonly GetProgramReferenceOptionsQuery $programs,
    ) {}

    public function index(Request $request, ListGpaHistoryQuery $query): Response
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'academic_standing' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentCampusId = session('current_campus_id');
        $filters = array_merge([
            'campus_id' => $currentCampusId,
            'per_page' => 15,
        ], array_filter($validated));

        $gpaRecords = $query->handle($filters);

        return Inertia::render('Admin/Academic/Gpa/History', [
            'gpaRecords' => $gpaRecords,
            'filters' => [
                'campus_id' => $filters['campus_id'],
                'semester_id' => $filters['semester_id'] ?? null,
                'program_id' => $filters['program_id'] ?? null,
                'academic_standing' => $filters['academic_standing'] ?? null,
                'search' => $filters['search'] ?? null,
                'per_page' => $filters['per_page'],
            ],
            'options' => [
                'campuses' => array_map(
                    static fn (CampusReference $campus): array => $campus->toArray(),
                    $this->campuses->all(),
                ),
                'semesters' => $this->semesters->records(),
                'programs' => $this->programs->options(),
                'standings' => [
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'warning', 'label' => 'Warning'],
                    ['value' => 'probation', 'label' => 'Probation'],
                    ['value' => 'suspension', 'label' => 'Suspension'],
                ],
            ],
        ]);
    }

    public function export(Request $request, ListGpaHistoryQuery $query, ExcelExportService $excelService): BinaryFileResponse
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'academic_standing' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $filters = array_filter($validated);

        // Get the builder with filters applied
        $builder = $query->getBuilder($filters);

        $export = new GpaHistoryExport($builder);
        $filename = 'gpa_history_'.date('Y-m-d_H-i');

        return $excelService->download($export, $filename);
    }
}
