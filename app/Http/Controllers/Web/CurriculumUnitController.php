<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Models\Unit;
use App\Http\Requests\StoreCurriculumUnitRequest;
use App\Http\Requests\UpdateCurriculumUnitRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use App\Constants\CurriculumRoutes;

class CurriculumUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'filter.curriculum_version_id' => 'nullable|exists:curriculum_versions,id',
            'filter.type' => 'nullable|in:core,major,elective',
            'sort' => 'nullable|string|in:created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $curriculumUnits = CurriculumUnit::query()
            ->with(['curriculumVersion.program', 'curriculumVersion.specialization', 'unit', 'semester'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->whereHas('unit', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($validated['filter']['curriculum_version_id'] ?? null, function ($query, $versionId) {
                $query->where('curriculum_version_id', $versionId);
            })
            ->when($validated['filter']['type'] ?? null, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('curriculum-units/Index', [
            'curriculumUnits' => $curriculumUnits,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'curriculum_version_id' => $validated['filter']['curriculum_version_id'] ?? null,
                'type' => $validated['filter']['type'] ?? null,
            ],
            'curriculumVersions' => CurriculumVersion::with(['program', 'specialization'])
                ->orderBy('version_code')
                ->get(['id', 'version_code', 'program_id', 'specialization_id']),
            'unitTypes' => [
                ['value' => 'core', 'label' => 'Core'],
                ['value' => 'major', 'label' => 'Major'],
                ['value' => 'elective', 'label' => 'Elective'],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('curriculum-units/Create', [
            'curriculumVersions' => CurriculumVersion::with(['program', 'specialization'])
                ->orderBy('version_code')
                ->get(['id', 'version_code', 'program_id', 'specialization_id']),
            'units' => Unit::orderBy('code')->get(['id', 'code', 'name', 'credit_points']),
            'semesters' => Semester::orderBy('start_date')->get(['id', 'name', 'code']),
            'unitTypes' => [
                ['value' => 'core', 'label' => 'Core'],
                ['value' => 'major', 'label' => 'Major'],
                ['value' => 'elective', 'label' => 'Elective'],
            ],
            'semesterOptions' => collect(range(1, 12))->map(fn($n) => ['value' => $n, 'label' => "Semester {$n}"]),
        ]);
    }

    public function store(StoreCurriculumUnitRequest $request): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $curriculumUnit = CurriculumUnit::create($request->validated());

            DB::commit();

            return redirect()
                ->route(CurriculumRoutes::UNIT_INDEX)
                ->with('success', 'Curriculum unit created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum unit creation failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create curriculum unit. Please try again.']);
        }
    }

    public function show(CurriculumUnit $curriculumUnit): Response
    {
        $curriculumUnit->load([
            'curriculumVersion.program',
            'curriculumVersion.specialization',
            'unit',
            'semester'
        ]);

        return Inertia::render('curriculum-units/Show', [
            'curriculumUnit' => $curriculumUnit,
        ]);
    }

    public function edit(CurriculumUnit $curriculumUnit): Response
    {
        $curriculumUnit->load(['curriculumVersion', 'unit', 'semester']);

        return Inertia::render('curriculum-units/Edit', [
            'curriculumUnit' => $curriculumUnit,
            'curriculumVersions' => CurriculumVersion::with(['program', 'specialization'])
                ->orderBy('version_code')
                ->get(['id', 'version_code', 'program_id', 'specialization_id']),
            'units' => Unit::orderBy('code')->get(['id', 'code', 'name', 'credit_points']),
            'semesters' => Semester::orderBy('start_date')->get(['id', 'name', 'code']),
            'unitTypes' => [
                ['value' => 'core', 'label' => 'Core'],
                ['value' => 'major', 'label' => 'Major'],
                ['value' => 'elective', 'label' => 'Elective'],
            ],
            'semesterOptions' => collect(range(1, 12))->map(fn($n) => ['value' => $n, 'label' => "Semester {$n}"]),
        ]);
    }

    public function update(UpdateCurriculumUnitRequest $request, CurriculumUnit $curriculumUnit): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $curriculumUnit->update($request->validated());

            DB::commit();

            return redirect()
                ->route(CurriculumRoutes::UNIT_INDEX)
                ->with('success', 'Curriculum unit updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum unit update failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update curriculum unit. Please try again.']);
        }
    }

    public function destroy(CurriculumUnit $curriculumUnit): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $curriculumUnit->delete();

            DB::commit();

            return redirect()
                ->route(CurriculumRoutes::UNIT_INDEX)
                ->with('success', 'Curriculum unit deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum unit deletion failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Failed to delete curriculum unit. Please try again.']);
        }
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'curriculum_unit_ids' => 'required|array|min:1|max:100',
            'curriculum_unit_ids.*' => 'integer|exists:curriculum_units,id',
        ]);

        try {
            DB::beginTransaction();

            $curriculumUnits = CurriculumUnit::whereIn('id', $validated['curriculum_unit_ids'])->get();
            $deleted = [];

            foreach ($curriculumUnits as $curriculumUnit) {
                $curriculumUnit->delete();
                $deleted[] = $curriculumUnit->id;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => count($deleted) . ' curriculum units deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'curriculum_version_id' => 'required|exists:curriculum_versions,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'type' => 'required|in:core,major,elective',
            'year_level' => 'required|integer|min:1|max:6',
            'semester_number' => 'required|integer|min:1|max:9',
            'note' => 'nullable|string|max:1000',
        ]);

        // Check for duplicate unit in curriculum
        $existingUnit = CurriculumUnit::where('curriculum_version_id', $validated['curriculum_version_id'])
            ->where('unit_id', $validated['unit_id'])
            ->first();

        if ($existingUnit) {
            return response()->json([
                'success' => false,
                'message' => 'This unit is already added to the curriculum version.',
                'error' => 'duplicate_unit'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $curriculumUnit = CurriculumUnit::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Curriculum unit created successfully.',
                'data' => $curriculumUnit->load(['unit', 'semester'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum unit API creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create curriculum unit. Please try again.'
            ], 500);
        }
    }

    public function apiUpdate(Request $request, CurriculumUnit $curriculumUnit)
    {
        $validated = $request->validate([
            'curriculum_version_id' => 'required|exists:curriculum_versions,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'type' => 'required|in:core,major,elective',
            'year_level' => 'required|integer|min:1|max:6',
            'semester_number' => 'required|integer|min:1|max:9',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $curriculumUnit->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Curriculum unit updated successfully.',
                'data' => $curriculumUnit->load(['unit', 'semester'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum unit API update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update curriculum unit. Please try again.'
            ], 500);
        }
    }

    public function getUnitsByCurriculumVersion(Request $request)
    {
        $validated = $request->validate([
            'curriculum_version_id' => 'required|exists:curriculum_versions,id',
        ]);

        $units = CurriculumUnit::with(['unit', 'semester'])
            ->where('curriculum_version_id', $validated['curriculum_version_id'])
            ->orderBy('semester_number')
            ->orderBy('created_at')
            ->get();

        return response()->json($units);
    }

    public function apiDestroy(CurriculumUnit $curriculumUnit)
    {
        try {
            DB::beginTransaction();

            $curriculumUnit->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Curriculum unit removed successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum unit API deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove curriculum unit. Please try again.'
            ], 500);
        }
    }
}
