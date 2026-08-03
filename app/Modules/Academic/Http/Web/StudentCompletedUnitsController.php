<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Exports\StudentCompletedUnitsExport;
use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Modules\Academic\Catalog\Queries\GetSemesterFilterOptionsQuery;
use App\Modules\Academic\Http\Requests\ListStudentCompletedUnitsRequest;
use App\Services\ExcelExportService;
use App\Shared\Contracts\Academic\ProgramReferenceReader;
use App\Shared\Contracts\Academic\StudentCompletedUnitsReader;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentCompletedUnitsController extends Controller
{
    public function index(
        ListStudentCompletedUnitsRequest $request,
        StudentCompletedUnitsReader $reader,
        ProgramReferenceReader $programs,
        GetSemesterFilterOptionsQuery $semesterOptions,
    ): Response {
        $filters = $this->resolveFilters($request);

        return Inertia::render('Academic/Report/StudentUnits/Index', [
            'report' => $reader->handle($filters),
            'filters' => [
                'active' => [
                    'program_id' => $filters['program_id'] ?? null,
                    'semester_id' => $filters['semester_id'] ?? null,
                    'keyword' => $filters['keyword'] ?? null,
                    'sort' => $filters['sort'],
                    'direction' => $filters['direction'],
                    'per_page' => $filters['per_page'],
                ],
                'options' => [
                    'programs' => collect($programs->all())
                        ->map(static fn (array $program): array => ['id' => $program['id'], 'name' => $program['name']])
                        ->values()
                        ->all(),
                    'semesters' => $semesterOptions->semesters()
                        ->map(static fn (Semester $semester): array => [
                            'id' => $semester->id,
                            'name' => $semester->name,
                            'code' => $semester->code,
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }

    public function export(
        ListStudentCompletedUnitsRequest $request,
        StudentCompletedUnitsReader $reader,
        ExcelExportService $excel,
    ): BinaryFileResponse {
        $filters = $this->resolveFilters($request);
        $rows = $reader->handleExport($filters);

        $exportFilters = array_filter([
            'campus_name' => Campus::find($filters['campus_id'])?->name,
            'keyword' => $filters['keyword'] ?? null,
            'program_name' => ! empty($filters['program_id']) ? Program::find($filters['program_id'])?->name : null,
            'semester_name' => ! empty($filters['semester_id']) ? Semester::find($filters['semester_id'])?->name : null,
        ]);

        $filename = 'student-registered-units-'.now()->format('Y-m-d-His');

        return $excel->download(
            new StudentCompletedUnitsExport($rows, $exportFilters, splitBySemester: empty($filters['semester_id'])),
            $filename,
        );
    }

    /**
     * Campus is never taken from client input — the FormRequest doesn't even
     * validate it, and the session is the only source of truth. Without this,
     * a crafted `campus_id` in the query string could pull another campus's
     * students into this report.
     *
     * @return array<string, mixed>
     */
    private function resolveFilters(ListStudentCompletedUnitsRequest $request): array
    {
        $validated = array_filter($request->validated(), static fn ($value) => $value !== null);

        return array_merge([
            'per_page' => 25,
            'sort' => 'student_id',
            'direction' => 'asc',
        ], $validated, [
            'campus_id' => session('current_campus_id'),
        ]);
    }
}
