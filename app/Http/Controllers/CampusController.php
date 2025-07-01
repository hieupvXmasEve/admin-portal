<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampusRequest;
use App\Http\Requests\UpdateCampusRequest;
use App\Models\Campus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class CampusController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view_campus')->only(['index', 'show']);
        $this->middleware('can:create_campus')->only(['create', 'store']);
        $this->middleware('can:edit_campus')->only(['edit', 'update']);
        $this->middleware('can:delete_campus')->only(['destroy']);
    }

    /**
     * Display a listing of campuses
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:name,code,address,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $campuses = Campus::query()
            ->withCount(['buildings', 'users'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('campuses/Index', [
            'campuses' => $campuses,
        ]);
    }

    /**
     * Show the form for creating a new campus
     */
    public function create(): Response
    {
        return Inertia::render('campuses/Create');
    }

    /**
     * Store a newly created campus
     */
    public function store(StoreCampusRequest $request): RedirectResponse
    {
        try {
            $campus = Campus::create($request->validated());

            return Redirect::route('campuses.index')
                ->with('success', 'Campus created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create campus', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create campus. Please try again.']);
        }
    }

    /**
     * Display the specified campus with building management
     */
    public function show(Request $request, Campus $campus): Response
    {
        // Validate building search and pagination parameters
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:name,code,description,address,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // Load campus with counts
        $campus->loadCount(['buildings', 'users']);

        // Get paginated buildings for this campus
        $buildings = $campus->buildings()
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('campuses/Show', [
            'campus' => $campus,
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for editing the specified campus
     */
    public function edit(Campus $campus): Response
    {
        return Inertia::render('campuses/Edit', [
            'campus' => $campus,
        ]);
    }

    /**
     * Update the specified campus
     */
    public function update(UpdateCampusRequest $request, Campus $campus): RedirectResponse
    {
        try {
            $campus->update($request->validated());

            return Redirect::route('campuses.index')
                ->with('success', 'Campus updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update campus', [
                'campus_id' => $campus->id,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update campus. Please try again.']);
        }
    }

    /**
     * Remove the specified campus
     */
    public function destroy(Campus $campus): RedirectResponse
    {
        try {
            // Check if campus has associated buildings
            if ($campus->buildings()->exists()) {
                return back()->withErrors(['error' => 'Cannot delete campus with associated buildings.']);
            }

            // Check if campus has associated users
            if ($campus->users()->exists()) {
                return back()->withErrors(['error' => 'Cannot delete campus with associated users.']);
            }

            $campus->delete();

            return Redirect::route('campuses.index')
                ->with('success', 'Campus deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete campus', [
                'campus_id' => $campus->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Failed to delete campus. Please try again.']);
        }
    }

    /**
     * Get campuses for API/dropdown usage
     */
    public function api(Request $request)
    {
        $campuses = Campus::select('id', 'name', 'code')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $campuses,
        ]);
    }
}
