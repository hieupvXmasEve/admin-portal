<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\ProgramRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Program\StoreProgramRequest;
use App\Http\Requests\Program\UpdateProgramRequest;
use App\Models\Program;
use App\Services\ProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $page = (int) $request->query('page', 1);

        $cacheKey = 'programs:index:'.md5(json_encode([
            'page' => $page,
            'per_page' => $validated['per_page'] ?? 15,
            'search' => $validated['search'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? 'asc',
        ]));
        $programs = Cache::tags(['programs', 'programs.index'])->rememberForever($cacheKey, function () use ($validated, $page) {
            return Program::query()
                ->when($validated['search'] ?? null, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                    $direction = $validated['direction'] ?? 'asc';
                    $query->orderBy($sort, $direction);
                })
                ->orderBy('created_at', 'desc')
                ->withCount(['specializations', 'curriculumVersions'])
                ->paginate($validated['per_page'] ?? 15, ['*'], 'page', $page);
        })->withQueryString();

        return Inertia::render('programs/Index', [
            'programs' => $programs,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'sort' => $validated['sort'] ?? '',
                'direction' => $validated['direction'] ?? 'asc',
                'per_page' => $validated['per_page'] ?? 15,
            ],
        ]);
    }

    public function store(StoreProgramRequest $request): RedirectResponse
    {
        try {
            $this->programService->createProgram($request->validated());

            // Invalidate program caches after mutation
            Cache::tags(['programs'])->flush();

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
        [$cachedProgram, $stats] = Cache::tags(['programs', 'programs.show'])
            ->rememberForever("programs:show:{$program->id}", function () use ($program) {
                $prog = $program->fresh()->load([
                    'specializations' => function ($query) {
                        $query->withCount('curriculumVersions')
                            ->orderBy('name');
                    },
                    'curriculumVersions' => function ($query) {
                        $query->with(['specialization', 'effectiveFromSemester'])
                            ->withCount('curriculumUnits')
                            ->orderBy('created_at', 'desc');
                    },
                ]);

                $statsLocal = [
                    'totalSpecializations' => $prog->specializations()->count(),
                    'activeSpecializations' => $prog->activeSpecializations()->count(),
                    'totalCurriculumVersions' => $prog->curriculumVersions()->count(),
                ];

                return [$prog, $statsLocal];
            });

        return Inertia::render('programs/Show', [
            'program' => $cachedProgram,
            'stats' => $stats,
        ]);
    }

    public function update(UpdateProgramRequest $request, Program $program): RedirectResponse
    {
        try {
            $this->programService->updateProgram($program, $request->validated());

            // Invalidate program caches after mutation
            Cache::tags(['programs'])->flush();

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

            // Invalidate program caches after deletion
            Cache::tags(['programs'])->flush();

            return redirect()
                ->route(ProgramRoutes::INDEX)
                ->with('success', 'Program deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Program deletion failed: ' . $e->getMessage());

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
