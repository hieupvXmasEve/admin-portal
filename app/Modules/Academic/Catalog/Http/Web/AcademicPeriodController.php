<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\SemesterRoutes;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Semester;
use App\Modules\Academic\Catalog\Actions\AcademicPeriodOperationException;
use App\Modules\Academic\Catalog\Actions\ActivateAcademicPeriodAction;
use App\Modules\Academic\Catalog\Actions\CreateAcademicPeriodAction;
use App\Modules\Academic\Catalog\Actions\DeactivateAcademicPeriodAction;
use App\Modules\Academic\Catalog\Actions\DeleteAcademicPeriodAction;
use App\Modules\Academic\Catalog\Actions\UpdateAcademicPeriodAction;
use App\Modules\Academic\Catalog\Http\Requests\ListAcademicPeriodsRequest;
use App\Modules\Academic\Catalog\Http\Requests\StoreAcademicPeriodRequest;
use App\Modules\Academic\Catalog\Http\Requests\UpdateAcademicPeriodRequest;
use App\Modules\Academic\Catalog\Queries\ListAcademicPeriodActivationStatusesQuery;
use App\Modules\Academic\Catalog\Queries\ListAcademicPeriodsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AcademicPeriodController extends Controller
{
    public function __construct(
        private readonly ListAcademicPeriodActivationStatusesQuery $activationStatuses,
        private readonly ListAcademicPeriodsQuery $listAcademicPeriods,
    ) {}

    public function index(ListAcademicPeriodsRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('semesters/Index', [
            'semesters' => $this->listAcademicPeriods->handle($filters),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'name' => $filters['filter']['name'] ?? null,
                'year' => $filters['filter']['year'] ?? null,
                'is_active' => $filters['filter']['is_active'] ?? null,
                'is_archived' => $filters['filter']['is_archived'] ?? null,
            ],
        ]);
    }

    public function store(StoreAcademicPeriodRequest $request): RedirectResponse
    {
        try {
            $result = CreateAcademicPeriodAction::run($request->validated());
        } catch (AcademicPeriodOperationException $exception) {
            return back()->withErrors(['is_active' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', $result['message']);

        return redirect()->route(SemesterRoutes::INDEX);
    }

    public function update(UpdateAcademicPeriodRequest $request, Semester $semester): RedirectResponse
    {
        $this->authorize('update', $semester);

        try {
            $result = UpdateAcademicPeriodAction::run($semester, $request->validated());
        } catch (AcademicPeriodOperationException $exception) {
            return back()->withErrors(['is_active' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', $result['message']);

        return redirect()->route(SemesterRoutes::INDEX);
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        $this->authorize('delete', $semester);

        try {
            DeleteAcademicPeriodAction::run($semester);
        } catch (AcademicPeriodOperationException $exception) {
            return back()->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', 'Semester deleted successfully!');

        return redirect()->route(SemesterRoutes::INDEX);
    }

    public function activate(Semester $semester): JsonResponse
    {
        $this->authorize('update', $semester);

        $result = ActivateAcademicPeriodAction::run($semester);

        return $result['success']
            ? ApiResponse::success(message: $result['message'])
            : ApiResponse::businessLogicError($result['message']);
    }

    public function deactivate(Semester $semester): JsonResponse
    {
        $this->authorize('update', $semester);

        $result = DeactivateAcademicPeriodAction::run($semester);

        return $result['success']
            ? ApiResponse::success(message: $result['message'])
            : ApiResponse::businessLogicError($result['message']);
    }

    public function activationStatuses(): JsonResponse
    {
        return ApiResponse::success($this->activationStatuses->handle());
    }

    public function apiUpdate(UpdateAcademicPeriodRequest $request, Semester $semester): JsonResponse
    {
        $this->authorize('update', $semester);

        try {
            $result = UpdateAcademicPeriodAction::run($semester, $request->validated());
        } catch (AcademicPeriodOperationException $exception) {
            return ApiResponse::validationError([
                'name' => [$exception->getMessage()],
            ], $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::serverError('An error occurred while updating the semester.');
        }

        return ApiResponse::success($result['academic_period'], message: $result['message']);
    }
}
