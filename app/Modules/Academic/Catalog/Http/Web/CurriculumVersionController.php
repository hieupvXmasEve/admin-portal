<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\CurriculumRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\DuplicateCurriculumVersionRequest;
use App\Http\Requests\StoreCurriculumVersionRequest;
use App\Http\Requests\UpdateCurriculumVersionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Modules\Academic\Catalog\Actions\CreateCurriculumVersionAction;
use App\Modules\Academic\Catalog\Actions\ManageCurriculumVersionApiAction;
use App\Modules\Academic\Catalog\Actions\ModifyCurriculumVersionAction;
use App\Modules\Academic\Catalog\Http\Requests\BulkCurriculumVersionOperationRequest;
use App\Modules\Academic\Catalog\Http\Requests\BulkDeleteCurriculumVersionsRequest;
use App\Modules\Academic\Catalog\Http\Requests\ExportCurriculumVersionsRequest;
use App\Modules\Academic\Catalog\Http\Requests\ListCurriculumVersionsByProgramRequest;
use App\Modules\Academic\Catalog\Http\Requests\ListCurriculumVersionsRequest;
use App\Modules\Academic\Catalog\Http\Requests\ListCurriculumVersionSummaryUnitsRequest;
use App\Modules\Academic\Catalog\Queries\GetCurriculumVersionPageDataQuery;
use App\Modules\Academic\Catalog\Queries\GetCurriculumVersionSummaryQuery;
use App\Modules\Academic\Catalog\Queries\ListCurriculumVersionsQuery;
use App\Shared\Contracts\StudentRegistry\CurriculumStudentSummaryReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalog owns Curriculum Version staff and API workflows.
 */
class CurriculumVersionController extends Controller
{
    public function index(ListCurriculumVersionsRequest $request): Response
    {
        $filters = $request->validated();
        $result = app(ListCurriculumVersionsQuery::class)->handle($filters);

        return Inertia::render('CurriculumVersions/Index', [
            'curriculumVersions' => $result['items'],
            'statistics' => $result['statistics'],
            'filters' => (object) [
                'search' => $filters['search'] ?? '',
                'program_id' => isset($filters['program_id']) ? (string) $filters['program_id'] : '',
                'specialization_id' => isset($filters['specialization_id']) ? (string) $filters['specialization_id'] : '',
                'sort' => $filters['sort'] ?? null,
                'direction' => $filters['direction'] ?? null,
                'per_page' => $filters['per_page'] ?? 15,
            ],
            'programs' => Program::query()->select('id', 'name', 'code')->orderBy('name')->get(),
            'specializations' => Specialization::query()->select('id', 'name', 'code', 'program_id')->orderBy('name')->get(),
            'semesters' => Semester::query()->select('id', 'name', 'code', 'start_date')->orderBy('start_date')->get(),
        ]);
    }

    public function store(StoreCurriculumVersionRequest $request): RedirectResponse
    {
        $curriculumVersion = app(CreateCurriculumVersionAction::class)->handle($request->validated());
        Inertia::flash('success', 'Curriculum version created successfully');

        return redirect()->route(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW, $curriculumVersion);
    }

    public function create(): Response
    {
        return Inertia::render('CurriculumVersions/Create', app(GetCurriculumVersionPageDataQuery::class)->create());
    }

    public function show(CurriculumVersion $curriculumVersion): RedirectResponse
    {
        return redirect()->route(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW, $curriculumVersion);
    }

    public function showWithModules(CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render('CurriculumVersions/Show', app(GetCurriculumVersionPageDataQuery::class)->show($curriculumVersion));
    }

    public function edit(CurriculumVersion $curriculumVersion): Response|RedirectResponse
    {
        $data = app(GetCurriculumVersionPageDataQuery::class)->edit($curriculumVersion);

        if (! $data['editable']) {
            Inertia::flash('error', 'Cannot edit a curriculum version that is already active or has passed.');

            return redirect()->route(CurriculumRoutes::VERSION_INDEX);
        }

        unset($data['editable']);

        return Inertia::render('CurriculumVersions/Edit', $data);
    }

    public function electiveManagement(CurriculumVersion $curriculumVersion): Response
    {
        $data = app(GetCurriculumVersionPageDataQuery::class)->electives($curriculumVersion);
        $availableElectives = $data['availableElectives'];

        return Inertia::render('CurriculumVersions/ElectiveManagement', [
            'curriculumVersion' => $data['curriculumVersion'],
            'electiveSlots' => $data['electiveSlots'],
            'availableElectives' => [
                'same_program_other_specializations' => [
                    'label' => 'Units from other specializations in the same program',
                    'units' => $availableElectives['same_program_other_specializations']->take(20),
                    'total_count' => $availableElectives['same_program_other_specializations']->count(),
                ],
                'cross_program_electives' => [
                    'label' => 'Units from other programs',
                    'units' => $availableElectives['cross_program_electives']->take(20),
                    'total_count' => $availableElectives['cross_program_electives']->count(),
                ],
                'general_electives' => [
                    'label' => 'General elective units',
                    'units' => $availableElectives['general_electives']->take(20),
                    'total_count' => $availableElectives['general_electives']->count(),
                ],
            ],
        ]);
    }

    public function summaryOverview(CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render(
            'CurriculumVersions/summary/Overview',
            app(GetCurriculumVersionSummaryQuery::class)->overview($curriculumVersion),
        );
    }

    public function summaryUnits(ListCurriculumVersionSummaryUnitsRequest $request, CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render(
            'CurriculumVersions/summary/Units',
            app(GetCurriculumVersionSummaryQuery::class)->units($curriculumVersion, $request->validated()),
        );
    }

    public function summaryModules(CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render(
            'CurriculumVersions/summary/Modules',
            app(GetCurriculumVersionSummaryQuery::class)->modules($curriculumVersion),
        );
    }

    public function summaryRoadmap(Request $request, CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render(
            'CurriculumVersions/summary/Roadmap',
            app(GetCurriculumVersionSummaryQuery::class)->roadmap($curriculumVersion),
        );
    }

    public function summaryStudents(CurriculumVersion $curriculumVersion): Response
    {
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
        ]);
        $studentStats = app(CurriculumStudentSummaryReader::class)->summaryForCurriculumVersion(
            $curriculumVersion->id,
            app('campus')->id,
        );

        return Inertia::render('CurriculumVersions/summary/Students', [
            'curriculumVersion' => [
                'id' => $curriculumVersion->id,
                'version_code' => $curriculumVersion->version_code,
                'program' => $curriculumVersion->program,
                'specialization' => $curriculumVersion->specialization,
                'effective_from_semester' => $curriculumVersion->effectiveFromSemester,
            ],
            'data' => $studentStats,
            'meta' => ['lastUpdatedAt' => now()->toISOString()],
            'links' => [
                'drillDown' => collect(['active', 'inactive', 'graduated', 'suspended', 'withdrawn'])
                    ->mapWithKeys(static fn (string $status): array => [$status => route('students.index', [
                        'curriculum_version_id' => $curriculumVersion->id,
                        'academic_status' => $status,
                    ])])
                    ->all(),
            ],
        ]);
    }

    public function update(
        UpdateCurriculumVersionRequest $request,
        CurriculumVersion $curriculumVersion,
    ): RedirectResponse {
        try {
            app(ModifyCurriculumVersionAction::class)->update($curriculumVersion, $request->validated());
        } catch (\DomainException $exception) {
            return redirect()->route(CurriculumRoutes::VERSION_INDEX)->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', 'Curriculum version updated successfully');

        return redirect()->route(CurriculumRoutes::VERSION_INDEX, $curriculumVersion);
    }

    public function destroy(CurriculumVersion $curriculumVersion): RedirectResponse
    {
        try {
            app(ModifyCurriculumVersionAction::class)->delete($curriculumVersion);
        } catch (\DomainException $exception) {
            return redirect()->route(CurriculumRoutes::VERSION_INDEX)->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', 'Curriculum version deleted successfully');

        return redirect()->route(CurriculumRoutes::VERSION_INDEX);
    }

    public function duplicate(
        DuplicateCurriculumVersionRequest $request,
        CurriculumVersion $curriculumVersion,
    ): RedirectResponse {
        $duplicatedVersion = app(ModifyCurriculumVersionAction::class)->duplicate($curriculumVersion, $request->validated());
        Inertia::flash('success', "Curriculum version duplicated successfully as '{$duplicatedVersion->version_code}'");

        return redirect()->route(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW, $duplicatedVersion);
    }

    public function exportFiltered(ExportCurriculumVersionsRequest $request): JsonResponse
    {
        try {
            $export = app(ManageCurriculumVersionApiAction::class)->export($request->validated());

            return ApiResponse::success($export, message: 'Export would be generated');
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Export failed: '.$exception->getMessage());
        }
    }

    public function bulkOperations(BulkCurriculumVersionOperationRequest $request): JsonResponse
    {
        $input = $request->validated();

        if ($input['action'] === 'delete') {
            return $this->deleteVersions($input['curriculum_version_ids']);
        }

        return ApiResponse::success([
            'filename' => 'selected_curriculum_versions_'.now()->format('Y_m_d_H_i_s').'.xlsx',
            'total_records' => count($input['curriculum_version_ids']),
        ], message: 'Selected curriculum versions exported successfully.');
    }

    public function getSpecializationsByProgram(ListCurriculumVersionsByProgramRequest $request): JsonResponse
    {
        return ApiResponse::success(
            app(ManageCurriculumVersionApiAction::class)->specializationsForProgram((int) $request->validated('program_id')),
        );
    }

    public function getCurriculumVersionsByProgramSpecialization(ListCurriculumVersionsByProgramRequest $request): JsonResponse
    {
        return ApiResponse::success(
            app(ManageCurriculumVersionApiAction::class)->versionsForProgram((int) $request->validated('program_id')),
        );
    }

    public function bulkDelete(BulkDeleteCurriculumVersionsRequest $request): JsonResponse
    {
        return $this->deleteVersions($request->validated('curriculum_version_ids'));
    }

    /** @param list<int> $curriculumVersionIds */
    private function deleteVersions(array $curriculumVersionIds): JsonResponse
    {
        try {
            $result = app(ManageCurriculumVersionApiAction::class)->bulkDelete($curriculumVersionIds);

            return ApiResponse::success($result, message: count($result['deleted']).' curriculum versions deleted successfully.');
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Bulk delete failed: '.$exception->getMessage());
        }
    }

    public function apiStore(StoreCurriculumVersionRequest $request): JsonResponse
    {
        try {
            $curriculumVersion = app(ManageCurriculumVersionApiAction::class)->create($request->validated());
            $curriculumVersion->load(['program:id,name', 'specialization:id,name', 'effectiveFromSemester:id,name,code']);

            return ApiResponse::success($curriculumVersion, message: 'Curriculum version created successfully.', status: 201);
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Failed to create curriculum version. Please try again.');
        }
    }

    public function apiDestroy(CurriculumVersion $curriculumVersion): JsonResponse
    {
        try {
            if (! app(ManageCurriculumVersionApiAction::class)->deleteIfEmpty($curriculumVersion)) {
                return ApiResponse::error('Cannot delete curriculum version with existing curriculum units.', [
                    ['code' => 'HAS_CURRICULUM_UNITS', 'field' => null, 'detail' => null],
                ]);
            }

            return ApiResponse::success(message: 'Curriculum version deleted successfully.');
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Failed to delete curriculum version. Please try again.');
        }
    }
}
