<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\SemesterRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiUpdateSemesterRequest;
use App\Models\Semester;
use App\Services\SemesterManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    public function index(Request $request): Response
    {
        // Convert string boolean values to actual booleans for validation
        $input = $request->all();
        if (isset($input['filter']['is_active'])) {
            if ($input['filter']['is_active'] === 'true') {
                $input['filter']['is_active'] = true;
            } elseif ($input['filter']['is_active'] === 'false') {
                $input['filter']['is_active'] = false;
            } elseif ($input['filter']['is_active'] === 'null' || $input['filter']['is_active'] === '') {
                $input['filter']['is_active'] = null;
            }
        }

        if (isset($input['filter']['is_archived'])) {
            if ($input['filter']['is_archived'] === 'true') {
                $input['filter']['is_archived'] = true;
            } elseif ($input['filter']['is_archived'] === 'false') {
                $input['filter']['is_archived'] = false;
            } elseif ($input['filter']['is_archived'] === 'null' || $input['filter']['is_archived'] === '') {
                $input['filter']['is_archived'] = null;
            }
        }

        // Validate input
        $validated = validator($input, [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'filter.name' => 'string|max:255',
            'filter.year' => 'string|max:4',
            'filter.is_active' => 'nullable|boolean',
            'filter.is_archived' => 'nullable|boolean',
        ])->validate();

        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;

        $query = Semester::query()->orderBy('start_date', 'desc');

        // Global search
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereRaw('YEAR(start_date) = ?', [$search])
                    ->orWhereRaw('YEAR(end_date) = ?', [$search]);
            });
        }

        // Column filters
        if (! empty($validated['filter'])) {
            foreach ($validated['filter'] as $column => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                switch ($column) {
                    case 'name':
                        $query->where('name', 'like', "%{$value}%");
                        break;
                    case 'year':
                        $query->where(function ($q) use ($value) {
                            $q->whereRaw('YEAR(start_date) = ?', [$value])
                                ->orWhereRaw('YEAR(end_date) = ?', [$value]);
                        });
                        break;
                    case 'is_active':
                        $query->where('is_active', $value);
                        break;
                    case 'is_archived':
                        $query->where('is_archived', $value);
                        break;
                }
            }
        }

        $semesters = $query->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('semesters/Index', [
            'semesters' => $semesters,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'name' => $validated['filter']['name'] ?? null,
                'year' => $validated['filter']['year'] ?? null,
                'is_active' => $validated['filter']['is_active'] ?? null,
                'is_archived' => $validated['filter']['is_archived'] ?? null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('semesters/Add');
    }

    public function store(Request $request): RedirectResponse
    {
        Log::info('Store semester request data:', $request->all());

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:semesters,code,NULL,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start_date' => 'nullable|date|before_or_equal:end_date',
            'enrollment_end_date' => 'nullable|date|after_or_equal:enrollment_start_date|before_or_equal:end_date',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ]);

        try {
            // Auto-deactivate expired semesters first
            Semester::deactivateExpiredSemesters();

            $semester = Semester::create(array_merge($validated, ['is_active' => false]));

            // If user wants to activate this semester, try to activate it
            if ($validated['is_active'] ?? false) {
                // Check if can change active status (although for new semester this should always be true)
                if (! $semester->canChangeActiveStatus()) {
                    $error = $semester->getActiveStatusChangeError();

                    return redirect()->back()->withErrors(['is_active' => $error]);
                }

                if ($semester->canBeActivated()) {
                    $semester->activate();
                    $message = 'Semester created and activated successfully!';
                } else {
                    $error = $semester->getActivationError();

                    return redirect()->back()->withErrors(['is_active' => $error]);
                }
            } else {
                $message = 'Semester created successfully!';
            }

            return redirect()->route(SemesterRoutes::INDEX)->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Error creating semester: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(Semester $semester): Response
    {
        return Inertia::render('semesters/Show', [
            'semester' => $semester,
        ]);
    }

    public function edit(Semester $semester): Response
    {
        return Inertia::render('semesters/Edit', [
            'semester' => $semester,
        ]);
    }

    public function update(Request $request, Semester $semester): RedirectResponse
    {
        Log::info('Update semester request data:', $request->all());

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:semesters,code,'.$semester->id,
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start_date' => 'nullable|date|before_or_equal:end_date',
            'enrollment_end_date' => 'nullable|date|after_or_equal:enrollment_start_date|before_or_equal:end_date',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ]);

        // Check for duplicate name (excluding current semester)
        $exists = Semester::where('name', $validated['name'])
            ->where('id', '!=', $semester->id)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['name' => 'A semester with this name already exists.']);
        }

        try {
            // Auto-deactivate expired semesters first
            Semester::deactivateExpiredSemesters();

            $wasActive = $semester->is_active;
            $wantsToBeActive = $validated['is_active'] ?? false;

            // Update basic fields first (excluding is_active)
            $updateData = array_merge($validated, ['is_active' => $semester->is_active]);
            $semester->update($updateData);

            // Handle activation/deactivation logic
            if ($wasActive !== $wantsToBeActive) {
                // User wants to change active status
                if (! $semester->canChangeActiveStatus()) {
                    $error = $semester->getActiveStatusChangeError();

                    return redirect()->back()->withErrors(['is_active' => $error]);
                }

                if (! $wasActive && $wantsToBeActive) {
                    // User wants to activate this semester
                    if ($semester->canBeActivated()) {
                        $semester->activate();
                        $message = 'Semester updated and activated successfully!';
                    } else {
                        $error = $semester->getActivationError();

                        return redirect()->back()->withErrors(['is_active' => $error]);
                    }
                } elseif ($wasActive && ! $wantsToBeActive) {
                    // User wants to deactivate this semester
                    $semester->deactivate();
                    $message = 'Semester updated and deactivated successfully!';
                }
            } else {
                $message = 'Semester updated successfully!';
            }

            return redirect()->route(SemesterRoutes::INDEX)->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Error updating semester: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        // Check if semester can be deleted using the model method
        if (! $semester->canDelete()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete this semester. It may be archived or currently active.']);
        }

        // Check if semester has enrollments
        if ($semester->enrollments()->exists()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete a semester with existing enrollments.']);
        }

        $semester->delete();

        return redirect()->route(SemesterRoutes::INDEX)->with('success', 'Semester deleted successfully!');
    }

    /**
     * Activate a semester via API
     */
    public function activate(Semester $semester, SemesterManagementService $service): JsonResponse
    {
        $result = $service->activateSemester($semester);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Deactivate a semester via API
     */
    public function deactivate(Semester $semester, SemesterManagementService $service): JsonResponse
    {
        $result = $service->deactivateSemester($semester);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Get semester activation statuses
     */
    public function activationStatuses(SemesterManagementService $service): JsonResponse
    {
        $statuses = $service->getSemesterActivationStatuses();

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Update semester via API
     */
    public function apiUpdate(ApiUpdateSemesterRequest $request, Semester $semester): JsonResponse
    {
        Log::info('API Update semester request data:', $request->validated());

        $validated = $request->validated();

        // Check for duplicate name (excluding current semester)
        $exists = Semester::where('name', $validated['name'])
            ->where('id', '!=', $semester->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A semester with this name already exists.',
                'errors' => ['name' => ['A semester with this name already exists.']],
            ], 422);
        }

        try {
            // Auto-deactivate expired semesters first
            Semester::deactivateExpiredSemesters();

            $wasActive = $semester->is_active;
            $wantsToBeActive = $validated['is_active'] ?? false;

            // Update basic fields first (excluding is_active)
            $updateData = array_merge($validated, ['is_active' => $semester->is_active]);
            $semester->update($updateData);

            // Handle activation/deactivation logic
            if ($wasActive !== $wantsToBeActive) {
                // User wants to change active status
                if (! $semester->canChangeActiveStatus()) {
                    $error = $semester->getActiveStatusChangeError();

                    return response()->json([
                        'success' => false,
                        'message' => $error,
                        'errors' => ['is_active' => [$error]],
                    ], 422);
                }

                if (! $wasActive && $wantsToBeActive) {
                    // User wants to activate this semester
                    if ($semester->canBeActivated()) {
                        $semester->activate();
                        $message = 'Semester updated and activated successfully!';
                    } else {
                        $error = $semester->getActivationError();

                        return response()->json([
                            'success' => false,
                            'message' => $error,
                            'errors' => ['is_active' => [$error]],
                        ], 422);
                    }
                } elseif ($wasActive && ! $wantsToBeActive) {
                    // User wants to deactivate this semester
                    $semester->deactivate();
                    $message = 'Semester updated and deactivated successfully!';
                }
            } else {
                $message = 'Semester updated successfully!';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $semester->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating semester via API: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the semester.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
