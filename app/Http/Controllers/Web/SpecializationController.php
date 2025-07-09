<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecializationRequest;
use App\Http\Requests\UpdateSpecializationRequest;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Services\SpecializationService;
use App\Constants\SpecializationRoutes;
use App\Constants\ProgramRoutes;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SpecializationController extends Controller
{
    public function __construct(protected SpecializationService $specializationService) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'program_id' => 'nullable|exists:programs,id',
            'sort' => 'nullable|string|in:name,code,created_at,program_name',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Specialization::with(['program:id,name'])
            ->withCount('curriculumVersions');

        // Search functionality
        if (!empty($validated['search'])) {
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
        if (!empty($validated['program_id'])) {
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
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];

        return Inertia::render('specializations/Index', [
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

    public function create(Request $request): Response
    {
        $programId = $request->query('program_id');

        return Inertia::render('specializations/Create', [
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'selectedProgramId' => $programId ? (int) $programId : null,
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
            Log::error('Specialization creation failed: ' . $e->getMessage());

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
            }
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

        return Inertia::render('specializations/Show', [
            'specialization' => $specialization,
            'statistics' => $statistics,
            'semesters' => Semester::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function edit(Specialization $specialization): Response
    {
        $specialization->load('program:id,name');

        return Inertia::render('specializations/Edit', [
            'specialization' => $specialization,
            'programs' => Program::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateSpecializationRequest $request, Specialization $specialization): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $specialization->update($request->validated());

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
            Log::error('Specialization update failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update specialization. Please try again.']);
        }
    }

    public function destroy(Specialization $specialization): RedirectResponse
    {
        try {
            // Check if specialization has any curriculum versions
            if ($specialization->curriculumVersions()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete specialization with existing curriculum versions.']);
            }

            DB::beginTransaction();

            $specializationName = $specialization->name;
            $specialization->delete();

            DB::commit();

            return redirect()
                ->route(SpecializationRoutes::INDEX)
                ->with('success', "Specialization '{$specializationName}' deleted successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Specialization deletion failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Failed to delete specialization. Please try again.']);
        }
    }

    public function apiDestroy(Specialization $specialization)
    {
        try {
            // Check if specialization has any curriculum versions
            if ($specialization->curriculumVersions()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete specialization with existing curriculum versions.'
                ], 400);
            }

            DB::beginTransaction();

            $specializationName = $specialization->name;
            $specialization->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Specialization '{$specializationName}' deleted successfully."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Specialization API deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete specialization. Please try again.'
            ], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'specialization_ids' => 'required|array|min:1|max:100',
            'specialization_ids.*' => 'integer|exists:specializations,id',
        ]);

        try {
            DB::beginTransaction();

            $specializations = Specialization::whereIn('id', $validated['specialization_ids'])->get();
            $deleted = [];
            $failed = [];

            foreach ($specializations as $specialization) {
                if ($specialization->curriculumVersions()->count() > 0) {
                    $failed[] = [
                        'name' => $specialization->name,
                        'reason' => 'Has existing curriculum versions'
                    ];
                } else {
                    $deleted[] = $specialization->name;
                    $specialization->delete();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'failed' => $failed,
                'message' => count($deleted) . ' specializations deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiDeleteCurriculumVersion(Request $request, $curriculumVersionId)
    {
        try {
            $curriculumVersion = \App\Models\CurriculumVersion::findOrFail($curriculumVersionId);

            // Check if curriculum version belongs to this specialization context
            $specializationId = $request->route('specialization');
            if ($specializationId && $curriculumVersion->specialization_id != $specializationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Curriculum version does not belong to this specialization.'
                ], 403);
            }

            DB::beginTransaction();

            $versionCode = $curriculumVersion->version_code;
            $curriculumVersion->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Curriculum version '{$versionCode}' deleted successfully."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum version deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete curriculum version. Please try again.'
            ], 500);
        }
    }

    public function apiUpdateCurriculumVersion(Request $request, $curriculumVersionId)
    {
        $validated = $request->validate([
            'version_code' => 'required|string|max:50',
            'semester_id' => 'nullable|exists:semesters,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $curriculumVersion = \App\Models\CurriculumVersion::findOrFail($curriculumVersionId);

            // Check if curriculum version belongs to this specialization context
            $specializationId = $request->route('specialization');
            if ($specializationId && $curriculumVersion->specialization_id != $specializationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Curriculum version does not belong to this specialization.'
                ], 403);
            }

            DB::beginTransaction();

            $curriculumVersion->update([
                'version_code' => $validated['version_code'],
                'semester_id' => $validated['semester_id'],
                'notes' => $validated['notes'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Curriculum version updated successfully.',
                'data' => $curriculumVersion->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum version update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update curriculum version. Please try again.'
            ], 500);
        }
    }
}
