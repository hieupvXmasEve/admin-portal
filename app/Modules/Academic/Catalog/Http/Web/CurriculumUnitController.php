<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\CurriculumRoutes;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Models\Unit;
use App\Modules\Academic\Catalog\Actions\CreateCurriculumUnitAction;
use App\Modules\Academic\Catalog\Actions\DeleteCurriculumUnitAction;
use App\Modules\Academic\Catalog\Actions\UpdateCurriculumUnitAction;
use App\Modules\Academic\Catalog\Http\Requests\ApiStoreCurriculumUnitRequest;
use App\Modules\Academic\Catalog\Http\Requests\ApiUpdateCurriculumUnitRequest;
use App\Modules\Academic\Catalog\Http\Requests\BulkDeleteCurriculumUnitsRequest;
use App\Modules\Academic\Catalog\Http\Requests\ListCurriculumUnitsRequest;
use App\Modules\Academic\Catalog\Http\Requests\ListCurriculumVersionUnitsRequest;
use App\Modules\Academic\Catalog\Http\Requests\StoreCurriculumUnitRequest;
use App\Modules\Academic\Catalog\Http\Requests\UpdateCurriculumUnitRequest;
use App\Modules\Academic\Catalog\Queries\ListCurriculumUnitsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CurriculumUnitController extends Controller
{
    public function __construct(private readonly ListCurriculumUnitsQuery $listCurriculumUnits) {}

    public function index(ListCurriculumUnitsRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('CurriculumUnits/Index', [
            'curriculumUnits' => $this->listCurriculumUnits->handle($filters),
            'filters' => (object) [
                'search' => $filters['search'] ?? '',
                'curriculum_version_id' => isset($filters['curriculum_version_id']) ? (string) $filters['curriculum_version_id'] : '',
                'unit_scope' => $filters['unit_scope'] ?? '',
                'sort' => $filters['sort'] ?? null,
                'direction' => $filters['direction'] ?? null,
                'per_page' => $filters['per_page'] ?? 15,
            ],
            'curriculumVersions' => $this->curriculumVersions(),
            'unitScopes' => $this->unitScopes(false),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CurriculumUnits/Create', $this->formOptions());
    }

    public function store(StoreCurriculumUnitRequest $request, CreateCurriculumUnitAction $create): RedirectResponse
    {
        try {
            $create->handle($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['error' => 'Failed to create curriculum unit. Please try again.']);
        }

        Inertia::flash('success', 'Curriculum unit created successfully.');

        return redirect()->route(CurriculumRoutes::UNIT_INDEX);
    }

    public function show(CurriculumUnit $curriculumUnit): Response
    {
        $curriculumUnit->load(['curriculumVersion.program', 'curriculumVersion.specialization', 'unit', 'semester']);

        return Inertia::render('CurriculumUnits/Show', ['curriculumUnit' => $curriculumUnit]);
    }

    public function edit(CurriculumUnit $curriculumUnit): Response
    {
        $curriculumUnit->load(['curriculumVersion', 'unit', 'semester']);

        return Inertia::render('CurriculumUnits/Edit', ['curriculumUnit' => $curriculumUnit, ...$this->formOptions()]);
    }

    public function update(UpdateCurriculumUnitRequest $request, CurriculumUnit $curriculumUnit, UpdateCurriculumUnitAction $update): RedirectResponse
    {
        try {
            $update->handle($curriculumUnit, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['error' => 'Failed to update curriculum unit. Please try again.']);
        }

        Inertia::flash('success', 'Curriculum unit updated successfully.');

        return redirect()->route(CurriculumRoutes::UNIT_INDEX);
    }

    public function destroy(CurriculumUnit $curriculumUnit, DeleteCurriculumUnitAction $delete): RedirectResponse
    {
        try {
            $delete->handle($curriculumUnit);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['error' => 'Failed to delete curriculum unit. Please try again.']);
        }

        Inertia::flash('success', 'Curriculum unit deleted successfully.');

        return redirect()->route(CurriculumRoutes::UNIT_INDEX);
    }

    public function bulkDelete(BulkDeleteCurriculumUnitsRequest $request, DeleteCurriculumUnitAction $delete): JsonResponse
    {
        try {
            $deleted = [];
            foreach (CurriculumUnit::query()->whereIn('id', $request->validated('curriculum_unit_ids'))->get() as $curriculumUnit) {
                $delete->handle($curriculumUnit);
                $deleted[] = $curriculumUnit->id;
            }
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Bulk delete failed: '.$exception->getMessage());
        }

        return ApiResponse::success(['deleted' => $deleted], message: count($deleted).' curriculum units deleted successfully.');
    }

    public function apiStore(ApiStoreCurriculumUnitRequest $request, CreateCurriculumUnitAction $create): JsonResponse
    {
        $data = $request->validated();
        if (CurriculumUnit::query()->where('curriculum_version_id', $data['curriculum_version_id'])->where('unit_id', $data['unit_id'])->exists()) {
            return ApiResponse::businessLogicError('This unit is already added to the curriculum version.', [
                ['code' => 'duplicate_unit', 'field' => 'unit_id', 'detail' => 'This unit is already added to the curriculum version.'],
            ]);
        }

        try {
            $curriculumUnit = $create->handle($data);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Failed to create curriculum unit. Please try again.');
        }

        return ApiResponse::success($curriculumUnit->load(['unit', 'semester']), message: 'Curriculum unit created successfully.', status: 201);
    }

    public function apiUpdate(ApiUpdateCurriculumUnitRequest $request, CurriculumUnit $curriculumUnit, UpdateCurriculumUnitAction $update): JsonResponse
    {
        try {
            $updated = $update->handle($curriculumUnit, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Failed to update curriculum unit. Please try again.');
        }

        return ApiResponse::success($updated->load(['unit', 'semester']), message: 'Curriculum unit updated successfully.');
    }

    public function getUnitsByCurriculumVersion(ListCurriculumVersionUnitsRequest $request): JsonResponse
    {
        return ApiResponse::success(CurriculumUnit::query()
            ->with(['unit', 'semester'])
            ->where('curriculum_version_id', $request->validated('curriculum_version_id'))
            ->orderBy('semester_number')
            ->orderBy('created_at')
            ->get());
    }

    public function apiDestroy(CurriculumUnit $curriculumUnit, DeleteCurriculumUnitAction $delete): JsonResponse
    {
        try {
            $delete->handle($curriculumUnit);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Failed to remove curriculum unit. Please try again.');
        }

        return ApiResponse::success(message: 'Curriculum unit removed successfully.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'curriculumVersions' => $this->curriculumVersions(),
            'units' => Unit::query()->orderBy('code')->get(['id', 'code', 'name', 'credit_points']),
            'semesters' => Semester::query()->orderBy('start_date')->get(['id', 'name', 'code']),
            'unitScopes' => $this->unitScopes(),
            'semesterOptions' => collect(range(1, 12))->map(fn (int $number): array => ['value' => $number, 'label' => "Semester {$number}"]),
        ];
    }

    private function curriculumVersions(): mixed
    {
        return CurriculumVersion::query()->with(['program', 'specialization'])
            ->orderBy('version_code')->get(['id', 'version_code', 'program_id', 'specialization_id']);
    }

    /** @return list<array{value: string, label: string}> */
    private function unitScopes(bool $includeCrossProgram = true): array
    {
        $scopes = [
            ['value' => 'program', 'label' => 'Program'],
            ['value' => 'common', 'label' => 'Common'],
            ['value' => 'specialization_specific', 'label' => 'Specialization Specific'],
        ];

        if ($includeCrossProgram) {
            $scopes[] = ['value' => 'cross_program', 'label' => 'Cross Program'];
        }

        return $scopes;
    }
}
