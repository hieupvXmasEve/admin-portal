<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\ProgramRoutes;
use App\Constants\SpecializationRoutes;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Modules\Academic\Catalog\Http\Requests\BulkDeleteSpecializationsRequest;
use App\Modules\Academic\Catalog\Http\Requests\ListSpecializationsRequest;
use App\Modules\Academic\Catalog\Http\Requests\StoreSpecializationRequest;
use App\Modules\Academic\Catalog\Http\Requests\UpdateSpecializationCurriculumVersionRequest;
use App\Modules\Academic\Catalog\Http\Requests\UpdateSpecializationRequest;
use App\Services\SpecializationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SpecializationController extends Controller
{
    public function __construct(protected SpecializationService $specializationService) {}

    public function index(ListSpecializationsRequest $request): Response
    {
        $validated = $request->validated();

        $query = Specialization::with(['program:id,name'])
            ->withCount('curriculumVersions');

        // Search functionality
        if (! empty($validated['search'])) {
            $searchTerm = $validated['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('code', 'like', "%{$searchTerm}%")
                    ->orWhereHas('program', function ($programQuery) use ($searchTerm) {
                        $programQuery->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Filter by program
        if (! empty($validated['program_id'])) {
            $query->where('program_id', $validated['program_id']);
        }

        // Sorting
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        if ($sort === 'program_name') {
            $query->join('programs', 'specializations.program_id', '=', 'programs.id')
                ->orderBy('programs.name', $direction)
                ->select('specializations.*');
        } else {
            $query->orderBy($sort, $direction);
        }

        $perPage = $validated['per_page'] ?? 15;
        $specializations = $query->paginate($perPage)->withQueryString();

        // Statistics
        $statistics = [
            'total_specializations' => Specialization::count(),
            'active_specializations' => Specialization::where('is_active', true)->count(),
            'inactive_specializations' => Specialization::where('is_active', false)->count(),
            'by_program' => Specialization::with('program:id,name')
                ->get()
                ->groupBy('program.name')
                ->map(fn ($group) => $group->count())
                ->toArray(),
        ];

        return Inertia::render('Specializations/Index', [
            'specializations' => $specializations,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'statistics' => $statistics,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Specializations/Create', [
            'programs' => Program::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreSpecializationRequest $request): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $specialization = $this->specializationService->createSpecialization($request->validated());

            DB::commit();

            // Check for source parameter to determine redirect destination
            $source = $request->query('source');
            $programId = $specialization->program_id;

            if ($source === 'program-show') {
                return redirect()
                    ->route(ProgramRoutes::SHOW, ['program' => $programId])
                    ->with('success', 'Specialization created successfully.');
            } elseif ($programId) {
                return redirect()
                    ->route(SpecializationRoutes::INDEX, ['program_id' => $programId])
                    ->with('success', 'Specialization created successfully.');
            } else {
                return redirect()
                    ->route(SpecializationRoutes::INDEX)
                    ->with('success', 'Specialization created successfully.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Specialization creation failed: '.$e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create specialization. Please try again.']);
        }
    }

    public function show(Specialization $specialization): Response
    {
        $specialization->load([
            'program:id,name',
            'curriculumVersions' => function ($query) {
                $query->with('effectiveFromSemester:id,name,code')
                    ->withCount('curriculumUnits')
                    ->orderBy('created_at', 'desc');
            },
        ]);

        // Statistics for this specialization
        $statistics = [
            'curriculum_versions_count' => $specialization->curriculumVersions->count(),
            'program_level_versions' => $specialization->program
                ->curriculumVersions()
                ->whereNull('specialization_id')
                ->count(),
            'specialization_level_versions' => $specialization->curriculumVersions
                ->whereNotNull('specialization_id')
                ->count(),
        ];

        return Inertia::render('Specializations/Show', [
            'specialization' => $specialization,
            'statistics' => $statistics,
            'semesters' => Semester::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function edit(Specialization $specialization): Response
    {
        $specialization->load('program:id,name');

        return Inertia::render('Specializations/Edit', [
            'specialization' => $specialization,
            'programs' => Program::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateSpecializationRequest $request, Specialization $specialization): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $this->specializationService->updateSpecialization($specialization, $request->validated());

            DB::commit();

            // Check for source parameter to determine redirect destination
            $source = $request->query('source');
            $programId = $specialization->program_id;

            if ($source === 'program-show') {
                return redirect()
                    ->route(ProgramRoutes::SHOW, ['program' => $programId])
                    ->with('success', 'Specialization updated successfully.');
            } elseif ($programId) {
                return redirect()
                    ->route(SpecializationRoutes::INDEX, ['program_id' => $programId])
                    ->with('success', 'Specialization updated successfully.');
            } else {
                return redirect()
                    ->route(SpecializationRoutes::INDEX)
                    ->with('success', 'Specialization updated successfully.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Specialization update failed: '.$e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update specialization. Please try again.']);
        }
    }

    public function destroy(Specialization $specialization): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $specializationName = $specialization->name;
            $this->specializationService->deleteSpecialization($specialization);

            DB::commit();

            return redirect()
                ->route(SpecializationRoutes::INDEX)
                ->with('success', "Specialization '{$specializationName}' deleted successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Specialization deletion failed: '.$e->getMessage());

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function apiDestroy(Specialization $specialization): JsonResponse
    {
        try {
            DB::beginTransaction();

            $specializationName = $specialization->name;
            $this->specializationService->deleteSpecialization($specialization);

            DB::commit();

            return ApiResponse::success(message: "Specialization '{$specializationName}' deleted successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Specialization API deletion failed: '.$e->getMessage());

            return ApiResponse::error($e->getMessage(), status: 500);
        }
    }

    public function bulkDelete(BulkDeleteSpecializationsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $result = $this->specializationService->bulkDeleteSpecializations($validated['specialization_ids']);

            DB::commit();

            // ApiResponse::compatible(): the flat deleted/failed envelope is pinned
            // by SpecializationManagementTest's raw-envelope characterization test.
            return ApiResponse::compatible([
                'success' => true,
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'message' => count($result['deleted']).' specializations deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::compatible([
                'success' => false,
                'message' => 'Bulk delete failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function apiUpdateCurriculumVersion(UpdateSpecializationCurriculumVersionRequest $request, $curriculumVersionId): JsonResponse
    {
        $validated = $request->validated();

        try {
            $curriculumVersion = CurriculumVersion::findOrFail($curriculumVersionId);

            // Check if curriculum version belongs to this specialization context
            $specializationId = $request->route('specialization');
            if ($specializationId && $curriculumVersion->specialization_id != $specializationId) {
                return ApiResponse::error('Curriculum version does not belong to this specialization.', status: 403);
            }

            DB::beginTransaction();

            $curriculumVersion->update([
                'version_code' => $validated['version_code'],
                'semester_id' => $validated['semester_id'] ?? $curriculumVersion->semester_id,
                'notes' => $validated['notes'] ?? $curriculumVersion->notes,
            ]);

            DB::commit();

            return ApiResponse::success($curriculumVersion->fresh(), message: 'Curriculum version updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum version update failed: '.$e->getMessage());

            return ApiResponse::error('Failed to update curriculum version. Please try again.', status: 500);
        }
    }
}
