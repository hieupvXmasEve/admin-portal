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
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
            'sort' => ['nullable', 'in:version_code,program_name,specialization_name,created_at,units_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $result = app(ListCurriculumVersionsQuery::class)->handle($filters);

        return Inertia::render('curriculum-versions/Index', [
            'curriculumVersions' => $result['items'],
            'statistics' => $result['statistics'],
            'filters' => $filters,
            'programs' => Program::query()->select('id', 'name', 'code')->orderBy('name')->get(),
            'specializations' => Specialization::query()->select('id', 'name', 'code', 'program_id')->orderBy('name')->get(),
            'semesters' => Semester::query()->select('id', 'name', 'code')->orderBy('name')->get(),
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
        return Inertia::render('curriculum-versions/Create', app(GetCurriculumVersionPageDataQuery::class)->create());
    }

    public function showWithModules(CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render('curriculum-versions/Show', app(GetCurriculumVersionPageDataQuery::class)->show($curriculumVersion));
    }

    public function edit(CurriculumVersion $curriculumVersion): Response|RedirectResponse
    {
        $data = app(GetCurriculumVersionPageDataQuery::class)->edit($curriculumVersion);

        if (! $data['editable']) {
            Inertia::flash('error', 'Cannot edit a curriculum version that is already active or has passed.');

            return redirect()->route(CurriculumRoutes::VERSION_INDEX);
        }

        unset($data['editable']);

        return Inertia::render('curriculum-versions/Edit', $data);
    }

    public function electiveManagement(CurriculumVersion $curriculumVersion): Response
    {
        $data = app(GetCurriculumVersionPageDataQuery::class)->electives($curriculumVersion);
        $availableElectives = $data['availableElectives'];

        return Inertia::render('curriculum-versions/ElectiveManagement', [
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
            'curriculum-versions/summary/Overview',
            app(GetCurriculumVersionSummaryQuery::class)->overview($curriculumVersion),
        );
    }

    public function summaryUnits(Request $request, CurriculumVersion $curriculumVersion): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'unit_scope' => ['nullable', 'in:program,common,specialization_specific,cross_program'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return Inertia::render(
            'curriculum-versions/summary/Units',
            app(GetCurriculumVersionSummaryQuery::class)->units($curriculumVersion, $filters),
        );
    }

    public function summaryModules(CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render(
            'curriculum-versions/summary/Modules',
            app(GetCurriculumVersionSummaryQuery::class)->modules($curriculumVersion),
        );
    }

    public function summaryRoadmap(Request $request, CurriculumVersion $curriculumVersion): Response
    {
        return Inertia::render(
            'curriculum-versions/summary/Roadmap',
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

        return Inertia::render('curriculum-versions/summary/Students', [
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

    public function exportFiltered(Request $request): JsonResponse
    {
        try {
            $export = app(ManageCurriculumVersionApiAction::class)->export($request->all());

            return ApiResponse::success($export, message: 'Export would be generated');
        } catch (\Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Export failed: '.$exception->getMessage());
        }
    }

    public function bulkOperations(Request $request): JsonResponse
    {
        $input = app(ManageCurriculumVersionApiAction::class)->validateBulkOperation($request->all());

        if ($input['action'] === 'delete') {
            return $this->bulkDelete($request);
        }

        return ApiResponse::success([
            'filename' => 'selected_curriculum_versions_'.now()->format('Y_m_d_H_i_s').'.xlsx',
            'total_records' => count($input['curriculum_version_ids']),
        ], message: 'Selected curriculum versions exported successfully.');
    }

    public function getSpecializationsByProgram(Request $request): JsonResponse
    {
        return ApiResponse::success(
            app(ManageCurriculumVersionApiAction::class)->specializationsForProgram($request->all()),
        );
    }

    public function getCurriculumVersionsByProgramSpecialization(Request $request): JsonResponse
    {
        return ApiResponse::success(
            app(ManageCurriculumVersionApiAction::class)->versionsForProgram($request->all()),
        );
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $result = app(ManageCurriculumVersionApiAction::class)->bulkDelete($request->all());

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
