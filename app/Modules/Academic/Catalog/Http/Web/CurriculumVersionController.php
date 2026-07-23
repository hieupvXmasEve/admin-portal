<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\CurriculumRoutes;
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
use App\Modules\Academic\Catalog\Queries\ListCurriculumVersionsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalog owns mutations while summary projections remain a compatibility
 * boundary during their staged query extraction.
 */
class CurriculumVersionController extends \App\Http\Controllers\Web\CurriculumVersionController
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
