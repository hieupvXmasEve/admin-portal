<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\UnitRoutes;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Modules\Academic\Catalog\Actions\BulkDeleteUnitsAction;
use App\Modules\Academic\Catalog\Actions\CreateUnitAction;
use App\Modules\Academic\Catalog\Actions\DeleteUnitAction;
use App\Modules\Academic\Catalog\Actions\UpdateUnitAction;
use App\Modules\Academic\Catalog\Http\Requests\BulkDeleteUnitsRequest;
use App\Modules\Academic\Catalog\Http\Requests\ListUnitsRequest;
use App\Modules\Academic\Catalog\Http\Requests\SearchUnitsRequest;
use App\Modules\Academic\Catalog\Http\Requests\StoreUnitRequest;
use App\Modules\Academic\Catalog\Http\Requests\UpdateUnitRequest;
use App\Modules\Academic\Catalog\Http\Requests\ValidateUnitCodeRequest;
use App\Modules\Academic\Catalog\Queries\GetUnitDetailQuery;
use App\Modules\Academic\Catalog\Queries\ListUnitsQuery;
use App\Modules\Academic\Catalog\Queries\SearchUnitsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UnitController extends Controller
{
    public function __construct(
        private readonly GetUnitDetailQuery $unitDetails,
        private readonly ListUnitsQuery $listUnits,
        private readonly SearchUnitsQuery $searchUnits,
        private readonly DeleteUnitAction $deleteUnit,
    ) {}

    public function index(ListUnitsRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('units/Index', [
            'units' => $this->listUnits->handle($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'sort' => $filters['sort'] ?? '',
                'direction' => $filters['direction'] ?? 'asc',
                'per_page' => $filters['per_page'] ?? 15,
                'type' => $filters['type'] ?? 'all',
                'level' => $filters['level'] ?? 'all',
            ],
            'statistics' => [
                'total_units' => Unit::query()->count(),
                'units_with_prerequisites' => Unit::query()->has('prerequisiteConditions')->count(),
                'units_with_equivalents' => Unit::query()->has('equivalentUnits')->count(),
                'avg_credit_points' => Unit::query()->avg('credit_points'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('units/Create', [
            'formDefaults' => ['code' => '', 'name' => '', 'credit_points' => 3],
        ]);
    }

    public function store(StoreUnitRequest $request, CreateUnitAction $createUnit): RedirectResponse
    {
        try {
            $createUnit->handle($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['error' => 'Failed to create unit: '.$exception->getMessage()]);
        }

        Inertia::flash('success', 'Unit created successfully.');

        return $this->redirectToReturnUrl($request->input('return'));
    }

    public function show(Unit $unit): Response
    {
        return Inertia::render('units/Show', [
            ...$this->unitDetails->handle($unit),
            'canEdit' => true,
            'canDelete' => $this->deleteUnit->eligibility($unit)['allowed'],
        ]);
    }

    public function edit(Unit $unit): Response
    {
        $details = $this->unitDetails->handle($unit);
        $unitData = $unit->toArray();

        return Inertia::render('units/Edit', [
            'unit' => $unitData,
            'prerequisiteDescriptions' => $details['prerequisiteDescriptions'],
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit, UpdateUnitAction $updateUnit): RedirectResponse
    {
        try {
            $updateUnit->handle($unit, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['error' => 'Failed to update unit: '.$exception->getMessage()]);
        }

        Inertia::flash('success', 'Unit updated successfully.');

        return $this->redirectToReturnUrl($request->input('return'));
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $eligibility = $this->deleteUnit->eligibility($unit);
        if (! $eligibility['allowed']) {
            return $this->redirectToReturnUrl($request->input('return'))->with('error', $eligibility['reason']);
        }

        try {
            $this->deleteUnit->handle($unit);
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectToReturnUrl($request->input('return'))
                ->with('error', 'Failed to delete unit: '.$exception->getMessage());
        }

        Inertia::flash('success', 'Unit deleted successfully.');

        return $this->redirectToReturnUrl($request->input('return'));
    }

    public function search(SearchUnitsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->searchUnits->handle($request->validated()),
            message: 'Units retrieved successfully',
        );
    }

    public function validateCode(ValidateUnitCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $exists = Unit::query()->where('code', $data['code'])
            ->when($data['unit_id'] ?? null, fn ($query, int $id) => $query->where('id', '!=', $id))
            ->exists();

        return ApiResponse::success([
            'valid' => ! $exists,
            'message' => $exists ? 'Unit code already exists' : 'Unit code is available',
        ]);
    }

    public function bulkDelete(BulkDeleteUnitsRequest $request, BulkDeleteUnitsAction $bulkDelete): JsonResponse
    {
        try {
            $result = $bulkDelete->handle($request->validated('unit_ids'));
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('Bulk delete failed: '.$exception->getMessage());
        }

        return ApiResponse::success([
            ...$result,
            'message' => count($result['deleted']).' units deleted successfully.',
        ]);
    }

    private function redirectToReturnUrl(?string $returnUrl): RedirectResponse
    {
        if ($returnUrl !== null && $this->isValidReturnUrl($returnUrl)) {
            return redirect($returnUrl);
        }

        return redirect()->route(UnitRoutes::INDEX);
    }

    private function isValidReturnUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            return $parsed['host'] === parse_url((string) config('app.url'), PHP_URL_HOST);
        }

        return str_starts_with($url, '/');
    }
}
