<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\Semester;
use App\Http\Requests\StoreCurriculumVersionRequest;
use App\Http\Requests\UpdateCurriculumVersionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumVersionController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'filter.program_id' => 'nullable|exists:programs,id',
            'filter.specialization_id' => 'nullable|exists:specializations,id',
            'filter.scope' => 'nullable|string|in:program,specialization',
            'sort' => 'nullable|string|in:version_code,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $curriculumVersions = CurriculumVersion::query()
            ->with(['program', 'specialization', 'effectiveFromSemester'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('version_code', 'like', "%{$search}%")
                        ->orWhereHas('program', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('specialization', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($validated['filter']['program_id'] ?? null, function ($query, $programId) {
                $query->where('program_id', $programId);
            })
            ->when($validated['filter']['specialization_id'] ?? null, function ($query, $specializationId) {
                $query->where('specialization_id', $specializationId);
            })
            ->when($validated['filter']['scope'] ?? null, function ($query, $scope) {
                $query->where('scope', $scope);
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->withCount('curriculumUnits')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('curriculum-versions/Index', [
            'curriculumVersions' => $curriculumVersions,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'program_id' => $validated['filter']['program_id'] ?? null,
                'specialization_id' => $validated['filter']['specialization_id'] ?? null,
                'scope' => $validated['filter']['scope'] ?? null,
            ],
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::with('program')->orderBy('name')->get(['id', 'name', 'program_id']),
            'scopeOptions' => [
                ['value' => 'program', 'label' => 'Program Level'],
                ['value' => 'specialization', 'label' => 'Specialization Level'],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('curriculum-versions/Create', [
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::with('program')->orderBy('name')->get(['id', 'name', 'program_id']),
            'semesters' => Semester::orderBy('name')->get(['id', 'name', 'code']),
            'scopeOptions' => [
                ['value' => 'program', 'label' => 'Program Level'],
                ['value' => 'specialization', 'label' => 'Specialization Level'],
            ],
        ]);
    }

    public function store(StoreCurriculumVersionRequest $request): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $curriculumVersion = CurriculumVersion::create($request->validated());

            DB::commit();

            return redirect()
                ->route('curriculum-versions.index')
                ->with('success', 'Curriculum version created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum version creation failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create curriculum version. Please try again.']);
        }
    }

    public function show(CurriculumVersion $curriculumVersion): Response
    {
        $curriculumVersion->load([
            'program',
            'specialization',
            'effectiveFromSemester',
            'curriculumUnits' => function ($query) {
                $query->with(['unit', 'unitType'])
                    ->orderBy('semester_order')
                    ->orderBy('created_at');
            }
        ]);

        return Inertia::render('curriculum-versions/Show', [
            'curriculumVersion' => $curriculumVersion,
        ]);
    }

    public function edit(CurriculumVersion $curriculumVersion): Response
    {
        $curriculumVersion->load(['program', 'specialization']);

        return Inertia::render('curriculum-versions/Edit', [
            'curriculumVersion' => $curriculumVersion,
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::with('program')->orderBy('name')->get(['id', 'name', 'program_id']),
            'semesters' => Semester::orderBy('name')->get(['id', 'name', 'code']),
            'scopeOptions' => [
                ['value' => 'program', 'label' => 'Program Level'],
                ['value' => 'specialization', 'label' => 'Specialization Level'],
            ],
        ]);
    }

    public function update(UpdateCurriculumVersionRequest $request, CurriculumVersion $curriculumVersion): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $curriculumVersion->update($request->validated());

            DB::commit();

            return redirect()
                ->route('curriculum-versions.index')
                ->with('success', 'Curriculum version updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum version update failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update curriculum version. Please try again.']);
        }
    }

    public function destroy(CurriculumVersion $curriculumVersion): RedirectResponse
    {
        try {
            // Check if curriculum version has any curriculum units
            if ($curriculumVersion->curriculumUnits()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete curriculum version with existing curriculum units.']);
            }

            DB::beginTransaction();

            $curriculumVersion->delete();

            DB::commit();

            return redirect()
                ->route('curriculum-versions.index')
                ->with('success', 'Curriculum version deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum version deletion failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Failed to delete curriculum version. Please try again.']);
        }
    }

    public function getSpecializationsByProgram(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $specializations = Specialization::where('program_id', $validated['program_id'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($specializations);
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'curriculum_version_ids' => 'required|array|min:1|max:100',
            'curriculum_version_ids.*' => 'integer|exists:curriculum_versions,id',
        ]);

        try {
            DB::beginTransaction();

            $curriculumVersions = CurriculumVersion::whereIn('id', $validated['curriculum_version_ids'])->get();
            $deleted = [];
            $failed = [];

            foreach ($curriculumVersions as $curriculumVersion) {
                if ($curriculumVersion->curriculumUnits()->count() > 0) {
                    $failed[] = [
                        'version_code' => $curriculumVersion->version_code,
                        'reason' => 'Has existing curriculum units'
                    ];
                } else {
                    $curriculumVersion->delete();
                    $deleted[] = $curriculumVersion->version_code;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'failed' => $failed,
                'message' => count($deleted) . ' curriculum versions deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
