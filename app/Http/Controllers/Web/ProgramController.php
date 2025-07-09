<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Http\Requests\Program\StoreProgramRequest;
use App\Http\Requests\Program\UpdateProgramRequest;
use App\Services\ProgramService;
use App\Constants\ProgramRoutes;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function __construct(protected ProgramService $programService) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:name,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $programs = Program::query()
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->withCount(['specializations', 'curriculumVersions'])
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('programs/Index', [
            'programs' => $programs,
            'filters' => [
                'search' => $validated['search'] ?? null,
            ],
        ]);
    }

    public function store(StoreProgramRequest $request): RedirectResponse
    {
        try {
            $this->programService->createProgram($request->validated());
            return redirect()
                ->route(ProgramRoutes::INDEX)
                ->with('success', 'Program created successfully.');
        } catch (\Exception $e) {
            Log::error('Program creation failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create program. Please try again.']);
        }
    }

    public function show(Program $program): Response
    {
        $program->load([
            'specializations' => function ($query) {
                $query->withCount('curriculumVersions')
                    ->orderBy('name');
            },
            'curriculumVersions' => function ($query) {
                $query->with(['specialization', 'effectiveFromSemester'])
                    ->withCount('curriculumUnits')
                    ->orderBy('created_at', 'desc');
            }
        ]);

        // Get program statistics
        $stats = [
            'totalSpecializations' => $program->specializations()->count(),
            'activeSpecializations' => $program->activeSpecializations()->count(),
            'totalCurriculumVersions' => $program->curriculumVersions()->count(),
        ];

        return Inertia::render('programs/Show', [
            'program' => $program,
            'stats' => $stats,
        ]);
    }

    public function update(UpdateProgramRequest $request, Program $program): RedirectResponse
    {
        try {
            $this->programService->updateProgram($program, $request->validated());
            return redirect()
                ->route(ProgramRoutes::INDEX)
                ->with('success', 'Program updated successfully.');
        } catch (\Exception $e) {
            Log::error('Program update failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update program. Please try again.']);
        }
    }

    public function destroy(Program $program): RedirectResponse
    {
        try {
            $this->programService->deleteProgram($program);
            return redirect()
                ->route(ProgramRoutes::INDEX)
                ->with('success', 'Program deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Program deletion failed: ' . $e->getMessage());
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $programs = Program::query()
            ->where('name', 'like', "%{$validated['q']}%")
            ->limit($validated['limit'] ?? 10)
            ->get(['id', 'name']);

        return response()->json($programs);
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'program_ids' => 'required|array|min:1|max:100',
            'program_ids.*' => 'integer|exists:programs,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $programs = Program::whereIn('id', $validated['program_ids'])->get();
                $deleted = [];
                $failed = [];

                foreach ($programs as $program) {
                    if ($program->specializations()->count() > 0 || $program->curriculumVersions()->count() > 0) {
                        $failed[] = [
                            'name' => $program->name,
                            'reason' => 'Has existing specializations or curriculum versions'
                        ];
                    } else {
                        $program->delete();
                        $deleted[] = $program->name;
                    }
                }

                return response()->json([
                    'success' => true,
                    'deleted' => $deleted,
                    'failed' => $failed,
                    'message' => count($deleted) . ' programs deleted successfully.'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
